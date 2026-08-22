const { readFileSync } = require('node:fs');
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');

const ratingSource = readFileSync(require.resolve('../src/js/rating.js'), 'utf8');

function createRating(markup, settings = {}) {
    const dom = new JSDOM(`<!doctype html><html><body>${markup}</body></html>`, {
        runScripts: 'outside-only',
    });
    dom.window.eval(ratingSource);

    const select = dom.window.document.querySelector('select[data-rating]');
    const instance = new dom.window.FormieRating({
        $field: select.parentElement,
        settings,
    });

    return {
        dom,
        instance,
        select,
        container: select.parentElement.querySelector('.fui-rating-field'),
    };
}

function starSelect({ value = '', half = true, type = 'star', min = 0, max = 5, attributes = '' } = {}) {
    const values = type === 'nps'
        ? Array.from({ length: 11 }, (_, index) => index)
        : half
            ? Array.from({ length: ((max - min) * 2) + 1 }, (_, index) => min + (index / 2))
            : Array.from({ length: (max - min) + 1 }, (_, index) => min + index);
    const options = values
        .map(option => `<option value="${option}"${String(option) === String(value) ? ' selected' : ''}>${option} label</option>`)
        .join('');

    return `<div><select id="rating" data-rating="${type}" ${attributes}><option value="">Choose</option>${options}</select></div>`;
}

test('zero is a distinct selectable choice and never fills a star', () => {
    const rating = createRating(starSelect({ value: '3.5' }));
    const stars = [...rating.container.querySelectorAll('.fui-rating-star-item')];
    const zero = rating.container.querySelector('.fui-rating-zero-item');

    assert.equal(stars.length, 5);
    assert.equal(zero.textContent, '0');
    assert.equal(stars[0].querySelector('.star-fill').style.opacity, '1');
    assert.equal(stars[2].querySelector('.star-fill').style.opacity, '1');
    assert.equal(stars[3].querySelector('.star-fill').style.clipPath, 'inset(0 50% 0 0)');
    assert.equal(stars[4].querySelector('.star-fill').style.opacity, '0');

    zero.click();

    assert.equal(rating.select.value, '0');
    assert.equal(zero.classList.contains('fui-rating-selected'), true);
    assert.equal(zero.getAttribute('aria-checked'), 'true');
    assert.equal(
        stars.some(star => star.querySelector('.star-fill').style.opacity === '1'),
        false,
    );
});

test('half-star interaction and redisplay preserve the normalized numeric value', () => {
    const rating = createRating(starSelect());
    const thirdStar = rating.container.querySelectorAll('.fui-rating-star-item')[2];
    thirdStar.getBoundingClientRect = () => ({ left: 0, width: 20 });
    thirdStar.dispatchEvent(new rating.dom.window.MouseEvent('click', {
        bubbles: true,
        clientX: 2,
    }));

    assert.equal(rating.select.value, '2.5');
    assert.equal(thirdStar.querySelector('.star-fill').style.clipPath, 'inset(0 50% 0 0)');

    const redisplayed = createRating(starSelect({ value: rating.select.value }));
    const redisplayedThirdStar = redisplayed.container.querySelectorAll('.fui-rating-star-item')[2];
    assert.equal(redisplayed.select.value, '2.5');
    assert.equal(redisplayedThirdStar.querySelector('.star-fill').style.opacity, '1');
    assert.equal(redisplayedThirdStar.querySelector('.star-fill').style.clipPath, 'inset(0 50% 0 0)');
});

test('minimum-above-one half ratings keep absolute display without exposing lower choices', () => {
    const rating = createRating(starSelect({ value: '2.5', min: 2 }));
    const stars = [...rating.container.querySelectorAll('.fui-rating-star-item')];

    assert.equal(stars.length, 5);
    assert.equal(stars[0].classList.contains('fui-rating-unavailable'), true);
    assert.equal(stars[0].querySelector('.star-fill').style.opacity, '1');
    assert.equal(stars[1].querySelector('.star-fill').style.opacity, '1');
    assert.equal(stars[2].querySelector('.star-fill').style.clipPath, 'inset(0 50% 0 0)');

    stars[0].click();
    assert.equal(rating.select.value, '2.5');

    stars[1].getBoundingClientRect = () => ({ left: 0, width: 20 });
    stars[1].dispatchEvent(new rating.dom.window.MouseEvent('click', {
        bubbles: true,
        clientX: 2,
    }));
    assert.equal(rating.select.value, '2');

    stars[2].getBoundingClientRect = () => ({ left: 0, width: 20 });
    stars[2].dispatchEvent(new rating.dom.window.MouseEvent('click', {
        bubbles: true,
        clientX: 2,
    }));
    assert.equal(rating.select.value, '2.5');

    rating.instance.selectRating(rating.select, 1.5, rating.container);
    assert.equal(rating.select.value, '2.5');

    const redisplayed = createRating(starSelect({ value: rating.select.value, min: 2 }));
    const redisplayedStars = redisplayed.container.querySelectorAll('.fui-rating-star-item');
    assert.equal(redisplayedStars[0].querySelector('.star-fill').style.opacity, '1');
    assert.equal(redisplayedStars[1].querySelector('.star-fill').style.opacity, '1');
    assert.equal(redisplayedStars[2].querySelector('.star-fill').style.clipPath, 'inset(0 50% 0 0)');
});

test('whole-star minimums zero, one, and above one retain absolute display and valid choices', () => {
    const zeroMinimum = createRating(starSelect({ value: '0', half: false, min: 0 }));
    const oneMinimum = createRating(starSelect({ value: '1', half: false, min: 1 }));
    const higherMinimum = createRating(starSelect({ value: '3', half: false, min: 2 }));
    const higherStars = [...higherMinimum.container.querySelectorAll('.fui-rating-star-item')];

    assert.equal(zeroMinimum.container.querySelector('.fui-rating-zero-item').getAttribute('aria-checked'), 'true');
    assert.equal(oneMinimum.container.querySelector('.fui-rating-zero-item'), null);
    assert.equal(oneMinimum.container.querySelectorAll('.fui-rating-star-item').length, 5);
    assert.equal(higherStars.length, 5);
    assert.equal(higherStars[0].classList.contains('fui-rating-unavailable'), true);
    assert.equal(higherStars[0].querySelector('.star-fill').style.opacity, '1');
    assert.equal(higherStars[2].querySelector('.star-fill').style.opacity, '1');

    higherStars[0].click();
    assert.equal(higherMinimum.select.value, '3');
    higherStars[1].click();
    assert.equal(higherMinimum.select.value, '2');
});

test('minimum-one half ratings fall back to the first configured whole value', () => {
    const rating = createRating(starSelect({ value: '1.5', min: 1 }));
    const firstStar = rating.container.querySelector('.fui-rating-star-item');

    assert.equal(firstStar.querySelector('.star-fill').style.opacity, '1');
    assert.equal(rating.container.querySelectorAll('.fui-rating-star-item')[1].querySelector('.star-fill').style.clipPath, 'inset(0 50% 0 0)');

    firstStar.getBoundingClientRect = () => ({ left: 0, width: 20 });
    firstStar.dispatchEvent(new rating.dom.window.MouseEvent('click', {
        bubbles: true,
        clientX: 2,
    }));
    assert.equal(rating.select.value, '1');
});

test('whole-star, emoji, and NPS modes keep their configured options', () => {
    const whole = createRating(starSelect({ value: '4', half: false }));
    const emoji = createRating(starSelect({ value: '3', half: false, type: 'emoji' }));
    const nps = createRating(starSelect({ value: '8', type: 'nps' }));

    assert.equal(whole.container.querySelectorAll('.fui-rating-zero-item').length, 1);
    assert.equal(whole.container.querySelectorAll('.fui-rating-star-item').length, 5);
    assert.equal(whole.container.querySelectorAll('.star-fill[style*="opacity: 1"]').length, 4);
    assert.equal(emoji.container.querySelectorAll('.fui-rating-item').length, 6);
    assert.equal(emoji.container.querySelector('[data-value="3"]').getAttribute('aria-checked'), 'true');
    assert.equal(nps.container.querySelectorAll('.fui-rating-item').length, 11);
    assert.equal(nps.container.querySelector('[data-value="8"]').getAttribute('aria-checked'), 'true');
});

test('keyboard and RTL half selection retain configured ordering behavior', () => {
    const keyboard = createRating(starSelect({ value: '0' }));
    keyboard.container.dispatchEvent(new keyboard.dom.window.KeyboardEvent('keydown', {
        key: 'ArrowRight',
        bubbles: true,
    }));
    assert.equal(keyboard.select.value, '0.5');

    const rtl = createRating(starSelect());
    rtl.dom.window.document.documentElement.dir = 'rtl';
    const thirdStar = [...rtl.container.querySelectorAll('.fui-rating-star-item')]
        .find(item => item.getAttribute('data-value') === '3');
    thirdStar.getBoundingClientRect = () => ({ left: 0, width: 20 });
    thirdStar.dispatchEvent(new rtl.dom.window.MouseEvent('click', {
        bubbles: true,
        clientX: 18,
    }));

    assert.equal(rtl.select.value, '2.5');
    assert.equal(thirdStar.querySelector('.star-fill').style.clipPath, 'inset(0 0 0 50%)');

    const higherMinimum = createRating(starSelect({ value: '2', min: 2 }));
    higherMinimum.container.dispatchEvent(new higherMinimum.dom.window.KeyboardEvent('keydown', {
        key: 'ArrowRight',
        bubbles: true,
    }));
    assert.equal(higherMinimum.select.value, '2.5');
    higherMinimum.container.dispatchEvent(new higherMinimum.dom.window.KeyboardEvent('keydown', {
        key: 'Home',
        bubbles: true,
    }));
    assert.equal(higherMinimum.select.value, '2');
});

test('required, disabled, and selected-label behavior remain attached to the select', () => {
    const required = createRating(starSelect({
        value: '2.5',
        min: 2,
        attributes: 'required data-rating-show-selected="true"',
    }));
    const disabled = createRating(starSelect({
        value: '2',
        min: 2,
        attributes: 'disabled',
    }));

    assert.equal(required.select.required, true);
    assert.equal(required.container.querySelector('.fui-rating-selected-label').textContent, '2.5 label');
    assert.equal(required.select.checkValidity(), true);
    assert.equal(
        [...disabled.container.querySelectorAll('.fui-rating-item')].every(item => item.disabled),
        true,
    );

    disabled.container.dispatchEvent(new disabled.dom.window.KeyboardEvent('keydown', {
        key: 'ArrowRight',
        bubbles: true,
    }));
    assert.equal(disabled.select.value, '2');

    required.instance.selectRating(required.select, 1.5, required.container);
    assert.equal(required.select.value, '2.5');
    assert.equal(required.select.checkValidity(), true);
});
