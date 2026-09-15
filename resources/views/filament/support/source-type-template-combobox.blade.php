<style>
    .mytree-source-type-combobox {
        position: relative;
        width: 100%;
    }

    .mytree-source-type-native-select {
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        padding: 0 !important;
        margin: -1px !important;
        overflow: hidden !important;
        clip: rect(0, 0, 0, 0) !important;
        white-space: nowrap !important;
        border: 0 !important;
    }

    .mytree-source-type-combobox-list {
        position: absolute;
        z-index: 80;
        top: calc(100% + 0.25rem);
        right: 0;
        left: 0;
        max-height: 16rem;
        overflow-y: auto;
        border: 1px solid rgb(209 213 219);
        border-radius: 0.5rem;
        background: rgb(255 255 255);
        box-shadow: 0 10px 20px rgb(0 0 0 / 0.12);
    }

    .dark .mytree-source-type-combobox-list {
        border-color: rgb(255 255 255 / 0.14);
        background: rgb(3 7 18);
    }

    .mytree-source-type-combobox-option {
        display: flex;
        width: 100%;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        border: 0;
        background: transparent;
        padding: 0.6rem 0.75rem;
        color: rgb(17 24 39);
        text-align: left;
        cursor: pointer;
    }

    .mytree-source-type-combobox-option:hover,
    .mytree-source-type-combobox-option:focus-visible {
        background: rgb(249 250 251);
        outline: none;
    }

    .dark .mytree-source-type-combobox-option {
        color: rgb(255 255 255);
    }

    .dark .mytree-source-type-combobox-option:hover,
    .dark .mytree-source-type-combobox-option:focus-visible {
        background: rgb(255 255 255 / 0.06);
    }

    .mytree-source-type-combobox-key {
        flex: none;
        color: rgb(107 114 128);
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.72rem;
    }

    .dark .mytree-source-type-combobox-key {
        color: rgb(156 163 175);
    }

    .mytree-source-type-combobox-empty {
        padding: 0.7rem 0.75rem;
        color: rgb(107 114 128);
        font-size: 0.8rem;
    }

    .dark .mytree-source-type-combobox-empty {
        color: rgb(156 163 175);
    }
</style>

<div data-mytree-source-type-template-combobox-assets hidden></div>

<script>
    (() => {
        const globalKey = '__mytreeSourceTypeTemplateCombobox';

        if (window[globalKey]) {
            window[globalKey].enhanceAll();
            return;
        }

        const modelPrefix = 'templateEditor.compatible_source_types.';
        const placeholder = @js(__('ui.settings.choose_source_type'));
        const noMatches = @js(__('ui.settings.no_source_type_matches'));
        let sequence = 0;

        const isSourceTypeSelect = (element) => {
            if (! (element instanceof HTMLSelectElement)) {
                return false;
            }

            const model = element.getAttribute('wire:model.live') ?? '';

            return model.startsWith(modelPrefix) && model.endsWith('.key');
        };

        const sourceTypeOptions = (select) => Array.from(select.options)
            .filter((option) => option.value !== '')
            .map((option) => ({
                value: option.value,
                label: option.textContent?.trim() ?? option.value,
            }));

        const enhance = (select) => {
            const existingWrapper = select.previousElementSibling;
            if (
                select.dataset.mytreeSourceTypeComboboxEnhanced === 'true'
                && existingWrapper?.matches('[data-mytree-source-type-template-combobox]')
            ) {
                return;
            }

            select.dataset.mytreeSourceTypeComboboxEnhanced = 'true';
            select.classList.add('mytree-source-type-native-select');
            select.setAttribute('aria-hidden', 'true');
            select.tabIndex = -1;

            const wrapper = document.createElement('div');
            wrapper.className = 'mytree-source-type-combobox';
            wrapper.dataset.mytreeSourceTypeTemplateCombobox = 'true';

            const input = document.createElement('input');
            input.type = 'search';
            input.className = 'mytree-drawer-input';
            input.placeholder = placeholder;
            input.autocomplete = 'off';
            input.setAttribute('role', 'combobox');
            input.setAttribute('aria-autocomplete', 'list');
            input.setAttribute('aria-expanded', 'false');
            input.setAttribute('aria-label', select.getAttribute('aria-label') ?? placeholder);

            const list = document.createElement('div');
            list.className = 'mytree-source-type-combobox-list';
            list.hidden = true;
            list.setAttribute('role', 'listbox');
            list.id = `mytree-source-type-combobox-${++sequence}`;
            input.setAttribute('aria-controls', list.id);

            wrapper.append(input, list);
            select.before(wrapper);

            const selectedOption = () => Array.from(select.options)
                .find((option) => option.value === select.value && option.value !== '');

            const syncLabel = () => {
                input.value = selectedOption()?.textContent?.trim() ?? '';
            };

            const close = ({ restore = true } = {}) => {
                list.hidden = true;
                input.setAttribute('aria-expanded', 'false');

                if (restore) {
                    syncLabel();
                }
            };

            const choose = (option) => {
                select.value = option.value;
                syncLabel();
                close({ restore: false });
                select.dispatchEvent(new Event('input', { bubbles: true }));
                select.dispatchEvent(new Event('change', { bubbles: true }));
                input.focus();
            };

            const renderOptions = (queryValue = input.value) => {
                const query = queryValue.trim().toLocaleLowerCase();
                const options = sourceTypeOptions(select).filter((option) => {
                    if (query === '') {
                        return true;
                    }

                    return option.label.toLocaleLowerCase().includes(query)
                        || option.value.toLocaleLowerCase().includes(query);
                });

                list.replaceChildren();

                if (options.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'mytree-source-type-combobox-empty';
                    empty.textContent = noMatches;
                    list.append(empty);
                } else {
                    options.forEach((option) => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.className = 'mytree-source-type-combobox-option';
                        button.setAttribute('role', 'option');
                        button.setAttribute('aria-selected', option.value === select.value ? 'true' : 'false');

                        const label = document.createElement('span');
                        label.textContent = option.label;

                        const key = document.createElement('span');
                        key.className = 'mytree-source-type-combobox-key';
                        key.textContent = option.value;

                        button.append(label, key);
                        button.addEventListener('mousedown', (event) => event.preventDefault());
                        button.addEventListener('click', () => choose(option));
                        list.append(button);
                    });
                }

                list.hidden = false;
                input.setAttribute('aria-expanded', 'true');
            };

            input.addEventListener('focus', () => {
                input.select();
                renderOptions('');
            });
            input.addEventListener('input', () => renderOptions());
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    close();
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    if (list.hidden) {
                        renderOptions('');
                    }
                    list.querySelector('button')?.focus();
                    return;
                }

                if (event.key === 'Enter' && ! list.hidden) {
                    const firstOption = list.querySelector('button');
                    if (firstOption instanceof HTMLButtonElement) {
                        event.preventDefault();
                        firstOption.click();
                    }
                }
            });

            wrapper.addEventListener('focusout', () => {
                window.setTimeout(() => {
                    if (! wrapper.contains(document.activeElement)) {
                        close();
                    }
                }, 0);
            });

            select.addEventListener('change', syncLabel);
            syncLabel();
        };

        const enhanceAll = () => {
            document.querySelectorAll('select').forEach((select) => {
                if (isSourceTypeSelect(select)) {
                    enhance(select);
                }
            });
        };

        const observer = new MutationObserver(() => enhanceAll());
        observer.observe(document.body, { childList: true, subtree: true });
        document.addEventListener('livewire:navigated', enhanceAll);

        window[globalKey] = { enhanceAll };
        enhanceAll();
    })();
</script>
