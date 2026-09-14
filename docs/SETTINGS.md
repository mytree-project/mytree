# Settings UI and localization

The administrator Settings page is a single-page configuration surface organized by capability rather than one large form.

## Categories

The current categories are:

- **General** — application-wide settings such as interface language,
- **Acquisition** — Source Acquisition presentation settings such as Source Type Templates.

The page should remain a single page until the amount of configuration makes separate category pages materially easier to use.

## Local save semantics

Settings do not use a page-wide submit operation.

Simple scalar settings are shown as values in their normal read state. Entering edit mode changes only that setting into the appropriate editor and exposes local **Save** and **Cancel** actions. Saving persists only the edited setting; cancelling reloads its persisted value.

This pattern is intentional so future Settings sections do not accidentally couple unrelated configuration changes into one transaction.

## Interface language and locale

The General section currently offers English (`en`) and Polish (`pl`). The selected interface language is persisted through the existing application `default_locale` setting and is applied to Filament requests by `ApplyApplicationLocale`.

For UI selection, stored locales whose primary language is Polish (for example `pl-PL`) map to the Polish interface; other currently supported values map to English. Saving through the Settings UI writes the canonical language locale (`en` or `pl`). This keeps the existing application settings contract compatible while tying locale-dependent presentation coherently to the selected interface language.

Repository-facing technical identifiers, historical source data and domain values are not translated merely because the UI language changes.

## Source Type Templates

Source Type Templates are shown as a compact list with basic identifying and status information. Selecting a row opens a right-side editor drawer. Creating a template uses the same drawer with a blank initial state.

Template Save/Cancel actions are local to the drawer. Persistence continues to use the existing `CreateSourceTypeTemplate` and `UpdateSourceTypeTemplate` application use cases, including optimistic version checks and immutable retained version history. The Settings UI remains presentation/configuration only and does not change Source/Mention/Claim evidence semantics.
