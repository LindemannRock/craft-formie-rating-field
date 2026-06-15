# Displaying ratings in templates

A Rating field stores a single number — the value the visitor picked. The interactive widget on the front end and the rich star/emoji/NPS display in the Control Panel and emails are all handled for you. This guide covers the two things you'd do in your own Twig: render the form, and read a submitted value.

## Render the form

The Rating field is a native Formie field, so there's nothing special to do — render the form the usual way and the rating widget appears:

```twig
{{ craft.formie.renderForm('contactForm') }}
```

The plugin registers its own CSS/JS to turn the underlying `<select>` into interactive stars, emoji, or NPS boxes, and (for the two Noto emoji modes) loads the matching font. See [The Rating field](../feature-tour/rating-field.md) for the appearance options.

## Read a submitted value

The stored value is a float (a half-star rating is `4.5`, an unanswered field is `null`). Access it by the field's handle on a submission:

```twig
{# The raw number the visitor chose #}
{{ submission.satisfaction }}      {# e.g. 4.5 #}

{# Guard against unanswered ratings #}
{% if submission.satisfaction is not null %}
    You rated us {{ submission.satisfaction }} out of {{ submission.getFieldByHandle('satisfaction').maxValue }}.
{% endif %}
```

Because it's a number, you can branch on it directly:

```twig
{% set score = submission.satisfaction %}
{% if score is not null %}
    {% if score >= 4 %}
        Thanks for the great rating!
    {% elseif score >= 2 %}
        Thanks for your feedback.
    {% else %}
        We're sorry to hear that — we'll do better.
    {% endif %}
{% endif %}
```

## Rich display (stars, emoji, NPS)

In the Control Panel submission view and in Formie notification emails, the value renders automatically as stars, an emoji, or an NPS box — you don't template that yourself. See [The Rating field → How a rating shows up afterwards](../feature-tour/rating-field.md#how-a-rating-shows-up-afterwards).

If you need that formatted string in your own template, the field exposes it via Formie's standard value methods (the field object comes from `submission.getFieldByHandle('<handle>')`):

```twig
{% set field = submission.getFieldByHandle('satisfaction') %}
{{ field.getValueAsString(submission.satisfaction, submission) }}   {# e.g. "★★★★½ (4.5/5)" #}
```

## Next steps

- [The Rating field](../feature-tour/rating-field.md) — every field option
- [Statistics](../feature-tour/statistics.md) — read ratings back as charts and scores
