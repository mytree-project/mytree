<div
    class="source-asset-viewer"
    x-data="{
        assets: @js($assetPreviews),
        index: 0,
        scale: 1,
        minScale: 1,
        maxScale: 4,
        x: 0,
        y: 0,
        naturalWidth: 0,
        naturalHeight: 0,
        viewportWidth: 0,
        viewportHeight: 0,
        dragging: false,
        dragPointerId: null,
        dragLastX: 0,
        dragLastY: 0,
        touchDragging: false,
        touchLastX: 0,
        touchLastY: 0,
        pinchStartDistance: 0,
        pinchStartScale: 1,
        pinchImageX: 0,
        pinchImageY: 0,
        resizeObserver: null,
        init() {
            this.resizeObserver = new ResizeObserver(() => this.reflow());
            this.$nextTick(() => this.observeViewport());
        },
        destroy() {
            this.resizeObserver?.disconnect();
        },
        current() {
            return this.assets[this.index] ?? null;
        },
        previous() {
            this.select(this.index - 1);
        },
        next() {
            this.select(this.index + 1);
        },
        select(nextIndex) {
            if (nextIndex < 0 || nextIndex >= this.assets.length || nextIndex === this.index) {
                return;
            }

            this.index = nextIndex;
            this.resetTransform();
            this.$nextTick(() => this.observeViewport());
        },
        resetTransform() {
            this.scale = 1;
            this.minScale = 1;
            this.x = 0;
            this.y = 0;
            this.naturalWidth = 0;
            this.naturalHeight = 0;
            this.viewportWidth = 0;
            this.viewportHeight = 0;
            this.dragging = false;
            this.touchDragging = false;
            this.pinchStartDistance = 0;
        },
        isImage() {
            return this.current()?.mime_type?.startsWith('image/');
        },
        isPdf() {
            return this.current()?.mime_type === 'application/pdf';
        },
        isAudio() {
            return this.current()?.mime_type?.startsWith('audio/');
        },
        isVideo() {
            return this.current()?.mime_type?.startsWith('video/');
        },
        viewport() {
            return this.$refs.imageViewport ?? null;
        },
        observeViewport() {
            const viewport = this.viewport();
            if (! viewport || ! this.resizeObserver) {
                return;
            }

            this.resizeObserver.disconnect();
            this.resizeObserver.observe(viewport);
        },
        imageLoaded(event) {
            this.naturalWidth = event.target.naturalWidth;
            this.naturalHeight = event.target.naturalHeight;
            this.observeViewport();
            this.$nextTick(() => this.fit());
        },
        fitScale(viewportWidth, viewportHeight) {
            if (this.naturalWidth <= 0 || this.naturalHeight <= 0 || viewportWidth <= 0 || viewportHeight <= 0) {
                return 1;
            }

            return Math.min(1, viewportWidth / this.naturalWidth, viewportHeight / this.naturalHeight);
        },
        fit() {
            const viewport = this.viewport();
            if (! viewport || this.naturalWidth <= 0 || this.naturalHeight <= 0) {
                return;
            }

            const rect = viewport.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0) {
                return;
            }

            this.viewportWidth = rect.width;
            this.viewportHeight = rect.height;
            this.minScale = this.fitScale(rect.width, rect.height);
            this.scale = this.minScale;
            this.x = (rect.width - (this.naturalWidth * this.scale)) / 2;
            this.y = (rect.height - (this.naturalHeight * this.scale)) / 2;
            this.clampPan();
        },
        reflow() {
            const viewport = this.viewport();
            if (! viewport || this.naturalWidth <= 0 || this.naturalHeight <= 0) {
                return;
            }

            const rect = viewport.getBoundingClientRect();
            if (rect.width <= 0 || rect.height <= 0) {
                return;
            }

            if (this.viewportWidth <= 0 || this.viewportHeight <= 0) {
                this.fit();
                return;
            }

            const oldMinScale = this.minScale;
            const oldScale = this.scale;
            const wasFit = Math.abs(oldScale - oldMinScale) < 0.001;
            const imageCenterX = ((this.viewportWidth / 2) - this.x) / oldScale;
            const imageCenterY = ((this.viewportHeight / 2) - this.y) / oldScale;

            this.viewportWidth = rect.width;
            this.viewportHeight = rect.height;
            this.minScale = this.fitScale(rect.width, rect.height);

            if (wasFit) {
                this.scale = this.minScale;
                this.x = (rect.width - (this.naturalWidth * this.scale)) / 2;
                this.y = (rect.height - (this.naturalHeight * this.scale)) / 2;
                this.clampPan();
                return;
            }

            this.scale = Math.min(this.maxScale, Math.max(this.minScale, oldScale));
            this.x = (rect.width / 2) - (imageCenterX * this.scale);
            this.y = (rect.height / 2) - (imageCenterY * this.scale);
            this.clampPan();
        },
        clampAxis(value, viewportSize, imageSize) {
            if (imageSize <= viewportSize) {
                return (viewportSize - imageSize) / 2;
            }

            return Math.min(0, Math.max(viewportSize - imageSize, value));
        },
        clampPan() {
            const viewport = this.viewport();
            if (! viewport || this.naturalWidth <= 0 || this.naturalHeight <= 0) {
                return;
            }

            const rect = viewport.getBoundingClientRect();
            this.viewportWidth = rect.width;
            this.viewportHeight = rect.height;
            this.x = this.clampAxis(this.x, rect.width, this.naturalWidth * this.scale);
            this.y = this.clampAxis(this.y, rect.height, this.naturalHeight * this.scale);
        },
        setScale(nextScale, clientX = null, clientY = null) {
            const viewport = this.viewport();
            if (! viewport || this.naturalWidth <= 0 || this.naturalHeight <= 0) {
                return;
            }

            const clampedScale = Math.min(this.maxScale, Math.max(this.minScale, nextScale));
            if (Math.abs(clampedScale - this.scale) < 0.0001) {
                return;
            }

            const rect = viewport.getBoundingClientRect();
            const focalX = clientX === null ? rect.width / 2 : clientX - rect.left;
            const focalY = clientY === null ? rect.height / 2 : clientY - rect.top;
            const imageX = (focalX - this.x) / this.scale;
            const imageY = (focalY - this.y) / this.scale;

            this.scale = clampedScale;
            this.x = focalX - (imageX * this.scale);
            this.y = focalY - (imageY * this.scale);
            this.clampPan();
        },
        zoomLabel() {
            return `${Math.round(this.scale * 100)}%`;
        },
        canPan() {
            const viewport = this.viewport();
            if (! viewport || this.naturalWidth <= 0 || this.naturalHeight <= 0) {
                return false;
            }

            const rect = viewport.getBoundingClientRect();

            return (this.naturalWidth * this.scale) > rect.width + 0.5
                || (this.naturalHeight * this.scale) > rect.height + 0.5;
        },
        panBy(deltaX, deltaY) {
            this.x += deltaX;
            this.y += deltaY;
            this.clampPan();
        },
        wheel(event) {
            if (! this.isImage() || (! event.ctrlKey && ! event.metaKey)) {
                return;
            }

            // Keep ordinary wheel/trackpad scrolling available to the surrounding workspace.
            // Ctrl/Cmd + wheel is reserved for focal-point image zoom.
            event.preventDefault();
            this.setScale(
                this.scale * Math.exp(-event.deltaY * 0.002),
                event.clientX,
                event.clientY,
            );
        },
        pointerDown(event) {
            if (event.pointerType === 'touch' || event.button !== 0 || ! this.canPan()) {
                return;
            }

            event.preventDefault();
            this.dragging = true;
            this.dragPointerId = event.pointerId;
            this.dragLastX = event.clientX;
            this.dragLastY = event.clientY;

            try {
                event.currentTarget.setPointerCapture?.(event.pointerId);
            } catch (error) {
                // Pointer capture is an enhancement; window-independent drag still works without it.
            }
        },
        pointerMove(event) {
            if (! this.dragging || event.pointerType === 'touch' || event.pointerId !== this.dragPointerId) {
                return;
            }

            event.preventDefault();
            this.panBy(event.clientX - this.dragLastX, event.clientY - this.dragLastY);
            this.dragLastX = event.clientX;
            this.dragLastY = event.clientY;
        },
        pointerEnd(event) {
            if (! this.dragging || event.pointerId !== this.dragPointerId) {
                return;
            }

            this.dragging = false;
            this.dragPointerId = null;

            try {
                event.currentTarget.releasePointerCapture?.(event.pointerId);
            } catch (error) {
                // The pointer may already have lost capture.
            }
        },
        touchDistance(touches) {
            return Math.hypot(
                touches[1].clientX - touches[0].clientX,
                touches[1].clientY - touches[0].clientY,
            );
        },
        touchMidpoint(touches) {
            return {
                x: (touches[0].clientX + touches[1].clientX) / 2,
                y: (touches[0].clientY + touches[1].clientY) / 2,
            };
        },
        touchStart(event) {
            if (event.touches.length >= 2) {
                const viewport = this.viewport();
                if (! viewport) {
                    return;
                }

                const distance = this.touchDistance(event.touches);
                if (distance <= 0) {
                    return;
                }

                event.preventDefault();
                const midpoint = this.touchMidpoint(event.touches);
                const rect = viewport.getBoundingClientRect();
                const localX = midpoint.x - rect.left;
                const localY = midpoint.y - rect.top;

                this.touchDragging = false;
                this.pinchStartDistance = distance;
                this.pinchStartScale = this.scale;
                this.pinchImageX = (localX - this.x) / this.scale;
                this.pinchImageY = (localY - this.y) / this.scale;
                return;
            }

            if (event.touches.length === 1 && this.canPan()) {
                event.preventDefault();
                this.touchDragging = true;
                this.touchLastX = event.touches[0].clientX;
                this.touchLastY = event.touches[0].clientY;
            }
        },
        touchMove(event) {
            if (event.touches.length >= 2 && this.pinchStartDistance > 0) {
                const viewport = this.viewport();
                if (! viewport) {
                    return;
                }

                event.preventDefault();
                const distance = this.touchDistance(event.touches);
                const midpoint = this.touchMidpoint(event.touches);
                const rect = viewport.getBoundingClientRect();
                const nextScale = Math.min(
                    this.maxScale,
                    Math.max(this.minScale, this.pinchStartScale * (distance / this.pinchStartDistance)),
                );

                this.scale = nextScale;
                this.x = (midpoint.x - rect.left) - (this.pinchImageX * this.scale);
                this.y = (midpoint.y - rect.top) - (this.pinchImageY * this.scale);
                this.clampPan();
                return;
            }

            if (event.touches.length === 1 && this.touchDragging && this.canPan()) {
                event.preventDefault();
                const touch = event.touches[0];
                this.panBy(touch.clientX - this.touchLastX, touch.clientY - this.touchLastY);
                this.touchLastX = touch.clientX;
                this.touchLastY = touch.clientY;
            }
        },
        touchEnd(event) {
            this.pinchStartDistance = 0;

            if (event.touches.length === 1 && this.canPan()) {
                this.touchDragging = true;
                this.touchLastX = event.touches[0].clientX;
                this.touchLastY = event.touches[0].clientY;
                return;
            }

            this.touchDragging = false;
        },
        touchCancel() {
            this.pinchStartDistance = 0;
            this.touchDragging = false;
        },
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
                    <button type="button" @click="setScale(scale / 1.25)" :disabled="scale <= minScale + .0001">−</button>
                    <span x-text="zoomLabel()">100%</span>
                    <button type="button" @click="setScale(scale * 1.25)" :disabled="scale >= maxScale - .0001">+</button>
                    <button type="button" @click="fit" :disabled="scale <= minScale + .0001">{{ __('ui.workspace.asset.reset') }}</button>
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
                    <div
                        x-ref="imageViewport"
                        class="source-asset-image-viewport"
                        :class="{ 'is-pannable': canPan(), 'is-dragging': dragging || touchDragging }"
                        :style="`touch-action: ${canPan() ? 'none' : 'pan-y'};`"
                        data-source-asset-image-viewport
                        data-source-asset-wheel-zoom="ctrl-or-meta"
                        @wheel="wheel($event)"
                        @pointerdown="pointerDown($event)"
                        @pointermove="pointerMove($event)"
                        @pointerup="pointerEnd($event)"
                        @pointercancel="pointerEnd($event)"
                        @touchstart="touchStart($event)"
                        @touchmove="touchMove($event)"
                        @touchend="touchEnd($event)"
                        @touchcancel="touchCancel"
                    >
                        <img
                            class="source-asset-image"
                            data-source-asset-image
                            :src="current()?.url"
                            :alt="current()?.filename ?? @js(__('ui.workspace.asset.alt'))"
                            :style="`transform: translate3d(${x}px, ${y}px, 0) scale(${scale});`"
                            draggable="false"
                            @load="imageLoaded($event)"
                            @dragstart.prevent
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

@once
    <style>
        .source-asset-image-viewport {
            position: relative;
            width: 100%;
            height: 100%;
            min-height: 28rem;
            overflow: hidden;
            user-select: none;
            -webkit-user-select: none;
        }
        .source-asset-image-viewport.is-pannable { cursor: grab; }
        .source-asset-image-viewport.is-dragging { cursor: grabbing; }
        .source-asset-image {
            position: absolute;
            top: 0;
            left: 0;
            display: block;
            max-width: none;
            max-height: none;
            transform-origin: top left;
            user-select: none;
            -webkit-user-select: none;
            -webkit-user-drag: none;
            will-change: transform;
        }
    </style>
@endonce
