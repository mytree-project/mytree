<x-filament-panels::page>
    <div class="space-y-6">
        <div class="space-y-2">
            <label for="mytree-search-query" class="text-sm font-medium text-gray-950 dark:text-white">
                {{ __('search.query_label') }}
            </label>
            <x-filament::input.wrapper>
                <x-filament::input
                    id="mytree-search-query"
                    type="search"
                    wire:model.live.debounce.300ms="query"
                    placeholder="{{ __('search.query_placeholder') }}"
                    autocomplete="off"
                />
            </x-filament::input.wrapper>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ __('search.query_hint') }}
            </p>
        </div>

        <section class="grid gap-4 rounded-xl border border-gray-200 p-4 dark:border-white/10 sm:grid-cols-2 lg:grid-cols-5" aria-label="{{ __('search.filters_title') }}">
            <div class="space-y-2">
                <label for="mytree-search-type" class="text-sm font-medium">{{ __('search.filter_source_type') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="mytree-search-type" type="text" wire:model.live.debounce.300ms="sourceTypeFilter" placeholder="civil.birth" />
                </x-filament::input.wrapper>
            </div>
            <div class="space-y-2">
                <label for="mytree-search-place" class="text-sm font-medium">{{ __('search.filter_place') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="mytree-search-place" type="text" wire:model.live.debounce.300ms="placeFilter" placeholder="{{ __('search.filter_place_example') }}" />
                </x-filament::input.wrapper>
            </div>
            <div class="space-y-2">
                <label for="mytree-search-from" class="text-sm font-medium">{{ __('search.filter_from') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="mytree-search-from" type="text" wire:model.live.debounce.300ms="dateFrom" placeholder="YYYY[-MM[-DD]]" />
                </x-filament::input.wrapper>
            </div>
            <div class="space-y-2">
                <label for="mytree-search-to" class="text-sm font-medium">{{ __('search.filter_to') }}</label>
                <x-filament::input.wrapper>
                    <x-filament::input id="mytree-search-to" type="text" wire:model.live.debounce.300ms="dateTo" placeholder="YYYY[-MM[-DD]]" />
                </x-filament::input.wrapper>
            </div>
            <div class="space-y-2">
                <label for="mytree-search-sort" class="text-sm font-medium">{{ __('search.sort_label') }}</label>
                <select
                    id="mytree-search-sort"
                    wire:model.live="sortMode"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 dark:border-white/20 dark:bg-gray-900 dark:text-white"
                >
                    <option value="relevance">{{ __('search.sort_relevance') }}</option>
                    <option value="source_date">{{ __('search.sort_source_date') }}</option>
                    <option value="source_added">{{ __('search.sort_source_added') }}</option>
                </select>
            </div>
        </section>
        @if ($this->hasInvalidDateFilter())
            <p class="text-sm text-danger-600 dark:text-danger-400" role="alert">{{ __('search.invalid_date_filter') }}</p>
        @endif

        @php($searchPage = $this->searchPage())

        @if ($searchPage === null)
            <div class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500 dark:border-white/20 dark:text-gray-400">
                {{ __('search.start_prompt') }}
            </div>
        @else
            @if ($searchPage->candidateWindowTruncated)
                <div class="rounded-xl border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200" role="status">
                    {{ __('search.candidate_window_truncated') }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 text-sm text-gray-500 dark:text-gray-400">
                <div>
                    @if ($searchPage->total !== null)
                        {{ trans_choice('search.result_count', $searchPage->total, ['count' => $searchPage->total]) }}
                    @else
                        {{ __('search.result_count_unknown') }}
                    @endif
                </div>
                <div>{{ __('search.page', ['page' => $searchPage->page]) }}</div>
            </div>

            @forelse ($searchPage->results as $item)
                @if ($item->details instanceof \App\Application\Search\SearchSourceResult)
                    @php($result = $item->details)
                <article
                    wire:key="search-result-{{ $item->resultKey }}"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <a
                                    href="{{ $this->sourceUrl($result) }}"
                                    class="text-base font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                >
                                    {{ $result->sourceName ?? __('search.untitled_source') }}
                                </a>
                                @if ($result->stale)
                                    <span class="rounded-full bg-warning-100 px-2 py-0.5 text-xs font-medium text-warning-800 dark:bg-warning-500/20 dark:text-warning-200">
                                        {{ __('search.stale') }}
                                    </span>
                                @endif
                            </div>
                            <div class="font-mono text-xs text-gray-500 dark:text-gray-400">
                                {{ $result->sourceId->value }}
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                {{ __('search.result_categories.'.$item->category->value) }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                {{ __('search.source_type', ['type' => $result->sourceTypeKey]) }}
                            </span>
                            <span class="rounded-full bg-gray-100 px-2 py-1 font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">
                                {{ __('search.score', ['score' => $result->score]) }}
                            </span>
                        </div>
                    </div>

                    <div class="mt-5 space-y-3">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ __('search.match_reasons') }}
                        </h3>

                        <div class="space-y-2">
                            @foreach ($result->matchReasons as $reason)
                                <div class="rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="font-semibold text-gray-950 dark:text-white">{{ $reason->matchedValue }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">· {{ $this->fieldLabel($reason->field) }}</span>
                                        <span class="rounded bg-gray-200 px-1.5 py-0.5 text-xs text-gray-700 dark:bg-white/10 dark:text-gray-300">
                                            {{ $this->matchTypeLabel($reason->type) }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                        <span>{{ __('search.query_term', ['term' => $reason->queryTerm]) }}</span>
                                        @if ($reason->matchedForm !== null && $reason->matchedForm !== $reason->matchedValue)
                                            <span>{{ __('search.matched_form', ['form' => $reason->matchedForm]) }}</span>
                                        @endif
                                        @if ($reason->similarity !== null)
                                            <span>{{ __('search.fuzzy_similarity', ['percent' => (int) round($reason->similarity * 100)]) }}</span>
                                        @endif
                                        @if ($reason->datasetId !== null)
                                            <span>{{ __('search.dictionary_origin', ['dataset' => $reason->datasetId, 'version' => $reason->datasetVersion, 'group' => $reason->variantGroupId]) }}</span>
                                        @endif
                                        @if ($reason->mentionId !== null)
                                            <span>{{ __('search.mention_id', ['id' => $reason->mentionId]) }}</span>
                                        @endif
                                        @if ($reason->claimId !== null)
                                            <span>{{ __('search.claim_id', ['id' => $reason->claimId]) }}</span>
                                        @endif
                                        @if ($reason->representationRelation !== null)
                                            <span>{{ __('search.representation_relation', ['relation' => $reason->representationRelation]) }}</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </article>
                @endif
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center text-sm text-gray-500 dark:border-white/20 dark:text-gray-400">
                    {{ __('search.no_results') }}
                </div>
            @endforelse

            @if ($searchPage->page > 1 || $searchPage->hasMore)
                <div class="flex items-center justify-between gap-4">
                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="previousResultPage"
                        :disabled="$searchPage->page <= 1"
                    >
                        {{ __('search.previous') }}
                    </x-filament::button>

                    <x-filament::button
                        type="button"
                        color="gray"
                        wire:click="nextResultPage"
                        :disabled="! $searchPage->hasMore"
                    >
                        {{ __('search.next') }}
                    </x-filament::button>
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
