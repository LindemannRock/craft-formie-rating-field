const { readFileSync } = require('node:fs');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');

const generatedScript = readFileSync(0, 'utf8');
let assertionCount = 0;
let scenarioCount = 0;

function equal(actual, expected) {
    assertionCount++;
    assert.equal(actual, expected);
}

function match(actual, expected) {
    assertionCount++;
    assert.match(actual, expected);
}

function createPage() {
    const dom = new JSDOM(`<!doctype html><html><body>
        <div id="first-wrap">
            <form id="first">
                <select name="fields[satisfaction]" data-formie-rating-google-review="3c4a8d56-51bf-41aa-8bbf-6876eb69547c">
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="5" selected>5</option>
                </select>
                <input name="fields[googlePlaceId]" value="Place / value">
                <button data-submit-action class="fui-btn first-button">Submit</button>
            </form>
            <div data-fui-alert-success>First unchanged</div>
        </div>
        <div id="second-wrap">
            <form id="second">
                <select name="fields[satisfaction]" data-formie-rating-google-review="b8e3c949-ae80-4fc2-97b6-8acb2db7f302">
                    <option value="2">2</option>
                    <option value="3">3</option>
                    <option value="4" selected>4</option>
                </select>
                <input name="fields[secondPlaceId]" value="Second / place">
                <button data-submit-action class="fui-btn second-button">Submit</button>
            </form>
            <div data-fui-alert-success>Second unchanged</div>
        </div>
        <div id="unrelated-wrap">
            <form id="unrelated"><input name="fields[comments]" value="Other"></form>
            <div data-fui-alert-success>Unrelated unchanged</div>
        </div>
        <div id="same-handle-without-owner-wrap">
            <form id="same-handle-without-owner">
                <select name="fields[satisfaction]"><option value="5" selected>5</option></select>
            </form>
            <div data-fui-alert-success>Same-handle unchanged</div>
        </div>
    `, { runScripts: 'outside-only', url: 'https://example.test/form' });

    dom.window.eval(generatedScript);
    for (const form of dom.window.document.querySelectorAll('form')) {
        dom.window.document.dispatchEvent(new dom.window.CustomEvent('onFormieInit', {
            detail: { $form: form },
        }));
    }

    return dom;
}

function submit(dom, formId) {
    const form = dom.window.document.getElementById(formId);
    form.dispatchEvent(new dom.window.CustomEvent('onBeforeFormieSubmit'));
    form.dispatchEvent(new dom.window.CustomEvent('onAfterFormieSubmit'));

    return new Promise(resolve => dom.window.setTimeout(resolve, 325));
}

async function run() {
    const unrelatedFirst = createPage();
    await submit(unrelatedFirst, 'unrelated');
    equal(unrelatedFirst.window.document.querySelector('#first-wrap [data-fui-alert-success]').textContent, 'First unchanged');
    equal(unrelatedFirst.window.document.querySelector('#second-wrap [data-fui-alert-success]').textContent, 'Second unchanged');
    equal(unrelatedFirst.window.document.querySelector('#unrelated-wrap [data-fui-alert-success]').textContent, 'Unrelated unchanged');
    scenarioCount++;

    await submit(unrelatedFirst, 'second');
    const secondAlert = unrelatedFirst.window.document.querySelector('#second-wrap [data-fui-alert-success]');
    equal(secondAlert.querySelector('p').textContent, 'SECOND HIGH');
    equal(secondAlert.querySelector('a').textContent, 'SECOND REVIEW');
    match(secondAlert.querySelector('a').href, /^https:\/\/example\.test\/review\/Second%20%2F%20place/);
    equal(secondAlert.querySelector('a').className, 'fui-btn second-button');
    equal(secondAlert.querySelector('a').rel, 'noopener noreferrer');
    equal(secondAlert.querySelector('div').className, 'flex justify-end');
    equal(unrelatedFirst.window.document.querySelector('#first-wrap [data-fui-alert-success]').textContent, 'First unchanged');
    equal(unrelatedFirst.window.document.querySelector('#unrelated-wrap [data-fui-alert-success]').textContent, 'Unrelated unchanged');
    scenarioCount++;

    await submit(unrelatedFirst, 'first');
    const firstAlert = unrelatedFirst.window.document.querySelector('#first-wrap [data-fui-alert-success]');
    equal(firstAlert.querySelector('p').textContent, 'HIGH <strong>text</strong>');
    equal(firstAlert.querySelector('strong'), null);
    equal(firstAlert.querySelector('a').textContent, 'REVIEW');
    equal(firstAlert.querySelector('a').className, 'fui-btn first-button');
    equal(firstAlert.querySelector('a').rel, 'noopener noreferrer');
    equal(firstAlert.querySelector('a').target, '_blank');
    match(firstAlert.querySelector('a').href, /^https:\/\/search\.google\.com\/local\/writereview\?placeid=Place%20%2F%20value/);
    equal(firstAlert.querySelector('div').className, 'flex justify-start');
    equal(unrelatedFirst.window.document.querySelector('#second-wrap [data-fui-alert-success] p').textContent, 'SECOND HIGH');
    scenarioCount++;

    const firstBeforeSecond = createPage();
    await submit(firstBeforeSecond, 'first');
    equal(firstBeforeSecond.window.document.querySelector('#first-wrap [data-fui-alert-success] p').textContent, 'HIGH <strong>text</strong>');
    equal(firstBeforeSecond.window.document.querySelector('#second-wrap [data-fui-alert-success]').textContent, 'Second unchanged');
    scenarioCount++;

    await submit(firstBeforeSecond, 'second');
    equal(firstBeforeSecond.window.document.querySelector('#second-wrap [data-fui-alert-success] p').textContent, 'SECOND HIGH');
    equal(firstBeforeSecond.window.document.querySelector('#first-wrap [data-fui-alert-success] p').textContent, 'HIGH <strong>text</strong>');
    scenarioCount++;

    await submit(firstBeforeSecond, 'same-handle-without-owner');
    equal(firstBeforeSecond.window.document.querySelector('#same-handle-without-owner-wrap [data-fui-alert-success]').textContent, 'Same-handle unchanged');
    equal(firstBeforeSecond.window.document.querySelector('#first-wrap [data-fui-alert-success] p').textContent, 'HIGH <strong>text</strong>');
    equal(firstBeforeSecond.window.document.querySelector('#second-wrap [data-fui-alert-success] p').textContent, 'SECOND HIGH');
    scenarioCount++;

    const tiers = createPage();
    const rating = tiers.window.document.querySelector('#first select');
    rating.value = '3';
    await submit(tiers, 'first');
    equal(tiers.window.document.querySelector('#first-wrap [data-fui-alert-success] p').textContent, 'MEDIUM');
    equal(tiers.window.document.querySelector('#second-wrap [data-fui-alert-success]').textContent, 'Second unchanged');
    scenarioCount++;

    rating.value = '2';
    await submit(tiers, 'first');
    equal(tiers.window.document.querySelector('#first-wrap [data-fui-alert-success] p').textContent, 'LOW');
    equal(tiers.window.document.querySelector('#second-wrap [data-fui-alert-success]').textContent, 'Second unchanged');
    scenarioCount++;

    rating.value = '5';
    tiers.window.document.querySelector('#first input[name="fields[googlePlaceId]"]').value = '';
    await submit(tiers, 'first');
    equal(tiers.window.document.querySelector('#first-wrap [data-fui-alert-success] p').textContent, 'MEDIUM');
    equal(tiers.window.document.querySelector('#first-wrap [data-fui-alert-success] a'), null);
    scenarioCount++;

    process.stdout.write(JSON.stringify({ scenarios: scenarioCount, assertions: assertionCount }));
}

run().catch(error => {
    process.stderr.write(`${error.stack || error.message}\n`);
    process.exitCode = 1;
});
