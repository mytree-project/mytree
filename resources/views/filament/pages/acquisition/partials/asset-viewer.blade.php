<div
    class="source-asset-viewer"
    x-data="{
        assets: @js($assetPreviews),
        index: 0,
        zoom: 1,
        current() { return this.assets[this.index] ?? null },
        previous() { if (this.index > 0) { this.index--; this.zoom = 1 } },
        next() { if (this.index < this.assets.length - 1) { this.index++; this.zoom = 1 } },
        isImage() { return this.current()?.mime_type?.startsWith('image/') },
        isPdf() { return this.current()?.mime_type === 'application/pdf' },
        isAudio() { return this.current()?.mime_type?.startsWith('audio/') },
        isVideo() { return this.current()?.mime_type?.startsWith('video/') },
    }"
    data-source-asset-viewer
>
    <template x-if="assets.length === 0">
        <div class="source-workspace-empty">
            {{ __('ui.workspace.asset.empty') }}
        </div>
    </template>

    <template x-if="assets.length > 0">
        <div class="source-asset-viewer-shell">
            <div class="source-asset-toolbar">
                <div class="source-asset-navigation">
                    <button type="button" @click="previous" :disabled="index === 0">{{ __('ui.workspace.asset.previous') }}</button>
                    <span><strong x-text="index + 1"></strong> / <span x-text="assets.length"></span></span>
                    <button type="button" @click="next" :disabled="index >= assets.length - 1">{{ __('ui.workspace.asset.next') }}</button>
                </div>

                <div class="source-asset-zoom" x-show="isImage()">
                    <button type="button" @click="zoom = Math.max(.5, zoom - .25)">−</button>
                    <span x-text="Math.round(zoom * 100) + '%'">100%</span>
                    <button type="button" @click="zoom = Math.min(4, zoom + .25)">+</button>
                    <button type="button" @click="zoom = 1">{{ __('ui.workspace.asset.reset') }}</button>
                </div>
            </div>

            <div class="source-asset-meta">
                <strong x-text="current()?.filename"></strong>
                <span x-text="current()?.mime_type"></span>
                <span x-text="current()?.size"></span>
                <a :href="current()?.url" target="_blank" rel="noopener">{{ __('ui.workspace.asset.open_original') }}</a>
            </div>

            <div class="source-asset-stage">
                <template x-if="isImage()">
                    <div class="source-asset-image-pan">
                        <img
                            :src="current()?.url"
                            :alt="current()?.filename ?? @js(__('ui.workspace.asset.alt'))"
                            :style="`transform: scale(${zoom}); transform-origin: top left;`"
                        >
                    </div>
                </template>

                <template x-if="isPdf()">
                    <iframe
                        class="source-asset-pdf"
                        :src="current()?.url + '#view=FitH'"
                        :title="current()?.filename ?? @js(__('ui.workspace.asset.pdf_title'))"
                    ></iframe>
                </template>

                <template x-if="isVideo()">
                    <video class="source-asset-media" controls :src="current()?.url"></video>
                </template>

                <template x-if="isAudio()">
                    <audio class="source-asset-audio" controls :src="current()?.url"></audio>
                </template>

                <template x-if="!isImage() && !isPdf() && !isVideo() && !isAudio()">
                    <div class="source-workspace-empty">
                        {{ __('ui.workspace.asset.unsupported') }}
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
