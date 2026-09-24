# Languages & Translations

PNLCS ships in **30 languages**. English, Turkish, Polish, Chinese and German
are complete; the others cover most of the interface and fall back to English
where a text is missing.

## Switch languages on

**Setup → Languages → Languages**

- **Progress** shows how much of each language is translated.
- Switch a language on to offer it to customers and staff.
- **Default Language**: the language new visitors and new customers get. The
  list offers every language that is switched on, and every language that is
  fully translated; choosing one that is off switches it on.

## Change a translation

Press **Translate** next to a language. The editor shows every text with its
English source; search by key or wording, filter by group, and edit in place.
Your changes are stored in the database and take priority over the files that
ship with PNLCS, so they survive updates.

If a change does not show at once, press **Clear Cache** on the Languages
page.

## Translate what is missing with AI

1. **Setup → Languages → Settings**: enter an **OpenAI API Key** and choose the
   **OpenAI Model**.
2. In a language's editor, press **AI Translate Missing**. Only texts without a
   translation are sent; your own translations are not touched.

Read the result before you rely on it: machine translation gets tone and
context wrong.

## Export and import

In a language's editor, **Export JSON** downloads every text; **Import** loads
a JSON file in the same shape. Use it to have a translator work offline.

## Contribute a translation

Sending your translation to the project puts it in every PNLCS install. See
[Translations](../developer/translations.md).
