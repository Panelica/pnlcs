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

## Translate with AI

1. **Setup → Languages**: the **AI Translation Settings** card at the top of the
   page takes an **OpenAI API Key** and the **OpenAI Model**. Save them once.
   The key is never shown again; leave the field empty to keep it.
2. Press **Translate** next to a language. The **Translate with AI** panel at
   the top of the editor offers two choices, each with the number of texts it
   covers:
    - **Only missing texts**: fills what the language has no text for. Nothing
      that is already there is touched.
    - **Everything again from English**: translates the whole language anew and
      replaces the current texts. Use it for a language whose texts you cannot
      trust.
3. Press **Start**. Texts go to OpenAI in batches of 30 and are saved as each
   batch comes back; the progress bar moves and every translated line appears
   in the panel as it arrives. **Stop** halts after the current batch, and
   **Continue** carries on from where it stopped, even after the page was
   closed.

A translation that drops or renames a placeholder (`:name`, `:amount`) is not
saved; the line shows as skipped and the text keeps what it had. Texts
translated this way are marked with a robot icon in the editor.

Read the result before you rely on it: machine translation gets tone and
context wrong. Correct any line in the editor and press **Save Changes**.

!!! note "Save stores only what you changed"
    The editor saves a line only when it differs from the text that ships with
    PNLCS. Setting a line back to the shipped text removes your override, so
    later improvements to the language reach you again.

## Export and import

In a language's editor, **Export JSON** downloads every text; **Import** loads
a JSON file in the same shape. Use it to have a translator work offline.

## Contribute a translation

Sending your translation to the project puts it in every PNLCS install. See
[Translations](../developer/translations.md).
