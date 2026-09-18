<?php

declare(strict_types=1);

use App\Infrastructure\Persistence\Eloquent\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Pest\Browser\Api\AwaitableWebpage;

function authenticateSourceImageViewerBrowserTestUser(): void
{
    $guardName = config('auth.defaults.guard');

    if (! is_string($guardName) || $guardName === '') {
        throw new RuntimeException('Default authentication guard is not configured.');
    }

    $auth = app(AuthFactory::class);
    $user = User::factory()->admin()->create([
        'email' => 'source-image-viewer-admin@example.test',
    ]);

    $auth->guard($guardName)->setUser($user);
    $auth->shouldUse($guardName);
}

it('fits high resolution images and supports focal zoom plus bounded mouse and touch panning', function (): void {
    authenticateSourceImageViewerBrowserTestUser();

    $pendingPage = visit('/admin/acquisition/source');
    $page = $pendingPage->__call('assertPresent', ['[data-source-asset-viewer]']);

    if (! $page instanceof AwaitableWebpage) {
        throw new RuntimeException('Browser visit did not resolve to an awaitable webpage.');
    }

    $page->script(<<<'JS'
        (() => {
            const viewer = document.querySelector('[data-source-asset-viewer]');
            if (! viewer || ! window.Alpine) {
                throw new Error('Source image viewer Alpine component is unavailable.');
            }

            const state = Alpine.$data(viewer);
            state.assets = [{
                id: 'browser-test-image',
                filename: 'high-resolution-test.svg',
                mime_type: 'image/svg+xml',
                size: '1 KB',
                url: "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='2400' height='1600' viewBox='0 0 2400 1600'%3E%3Crect width='2400' height='1600' fill='white'/%3E%3C/svg%3E",
            }];
        })()
        JS);

    $page
        ->assertPresent('[data-source-asset-image-viewport]')
        ->assertScript(
            'document.querySelector("[data-source-asset-image]")?.naturalWidth === 2400',
            true,
        )
        ->assertScript(<<<'JS'
            (() => {
                const viewer = document.querySelector('[data-source-asset-viewer]');
                const viewport = document.querySelector('[data-source-asset-image-viewport]');
                const state = Alpine.$data(viewer);

                return state.naturalWidth === 2400
                    && state.naturalHeight === 1600
                    && Math.abs(state.scale - state.minScale) < 0.001
                    && (state.naturalWidth * state.scale) <= viewport.clientWidth + 1
                    && (state.naturalHeight * state.scale) <= viewport.clientHeight + 1;
            })()
            JS, true);

    $page->script(<<<'JS'
        (() => {
            const viewer = document.querySelector('[data-source-asset-viewer]');
            const viewport = document.querySelector('[data-source-asset-image-viewport]');
            const state = Alpine.$data(viewer);
            const rect = viewport.getBoundingClientRect();
            const clientX = rect.left + (rect.width * 0.72);
            const clientY = rect.top + (rect.height * 0.38);
            const fitScale = state.scale;

            const ordinaryWheel = new WheelEvent('wheel', {
                bubbles: true,
                cancelable: true,
                clientX,
                clientY,
                deltaY: -160,
            });
            viewport.dispatchEvent(ordinaryWheel);

            const afterOrdinaryWheel = state.scale;
            const modifiedWheel = new WheelEvent('wheel', {
                bubbles: true,
                cancelable: true,
                clientX,
                clientY,
                ctrlKey: true,
                deltaY: -160,
            });
            viewport.dispatchEvent(modifiedWheel);

            window.__sourceImageViewerWheel = {
                fitScale,
                afterOrdinaryWheel,
                afterModifiedWheel: state.scale,
                ordinaryPrevented: ordinaryWheel.defaultPrevented,
                modifiedPrevented: modifiedWheel.defaultPrevented,
            };
        })()
        JS);

    $page
        ->assertScript('window.__sourceImageViewerWheel.afterOrdinaryWheel === window.__sourceImageViewerWheel.fitScale', true)
        ->assertScript('window.__sourceImageViewerWheel.ordinaryPrevented', false)
        ->assertScript('window.__sourceImageViewerWheel.afterModifiedWheel > window.__sourceImageViewerWheel.fitScale', true)
        ->assertScript('window.__sourceImageViewerWheel.modifiedPrevented', true);

    $page->script(<<<'JS'
        (() => {
            const viewer = document.querySelector('[data-source-asset-viewer]');
            const viewport = document.querySelector('[data-source-asset-image-viewport]');
            const state = Alpine.$data(viewer);
            const rect = viewport.getBoundingClientRect();

            state.setScale(1, rect.left + (rect.width / 2), rect.top + (rect.height / 2));
            const beforeX = state.x;
            const beforeY = state.y;
            const pointerId = 71;
            const startX = rect.left + (rect.width / 2);
            const startY = rect.top + (rect.height / 2);

            viewport.dispatchEvent(new PointerEvent('pointerdown', {
                bubbles: true,
                cancelable: true,
                pointerId,
                pointerType: 'mouse',
                button: 0,
                clientX: startX,
                clientY: startY,
            }));
            viewport.dispatchEvent(new PointerEvent('pointermove', {
                bubbles: true,
                cancelable: true,
                pointerId,
                pointerType: 'mouse',
                clientX: startX - 80,
                clientY: startY - 55,
            }));
            viewport.dispatchEvent(new PointerEvent('pointerup', {
                bubbles: true,
                cancelable: true,
                pointerId,
                pointerType: 'mouse',
                clientX: startX - 80,
                clientY: startY - 55,
            }));

            const movedByDrag = state.x < beforeX || state.y < beforeY;

            state.panBy(-100000, -100000);
            const lowerRect = viewport.getBoundingClientRect();
            const lowerImageWidth = state.naturalWidth * state.scale;
            const lowerImageHeight = state.naturalHeight * state.scale;
            const expectedLowerX = lowerImageWidth <= lowerRect.width
                ? (lowerRect.width - lowerImageWidth) / 2
                : lowerRect.width - lowerImageWidth;
            const expectedLowerY = lowerImageHeight <= lowerRect.height
                ? (lowerRect.height - lowerImageHeight) / 2
                : lowerRect.height - lowerImageHeight;
            const lowerBounded = Math.abs(state.x - expectedLowerX) <= 1
                && Math.abs(state.y - expectedLowerY) <= 1;

            state.panBy(100000, 100000);
            const upperRect = viewport.getBoundingClientRect();
            const upperImageWidth = state.naturalWidth * state.scale;
            const upperImageHeight = state.naturalHeight * state.scale;
            const expectedUpperX = upperImageWidth <= upperRect.width
                ? (upperRect.width - upperImageWidth) / 2
                : 0;
            const expectedUpperY = upperImageHeight <= upperRect.height
                ? (upperRect.height - upperImageHeight) / 2
                : 0;
            const upperBounded = Math.abs(state.x - expectedUpperX) <= 1
                && Math.abs(state.y - expectedUpperY) <= 1;

            window.__sourceImageViewerPointer = {
                movedByDrag,
                lowerBounded,
                upperBounded,
                draggingEnded: state.dragging === false,
            };
        })()
        JS);

    $page
        ->assertScript('window.__sourceImageViewerPointer.movedByDrag', true)
        ->assertScript('window.__sourceImageViewerPointer.lowerBounded', true)
        ->assertScript('window.__sourceImageViewerPointer.upperBounded', true)
        ->assertScript('window.__sourceImageViewerPointer.draggingEnded', true);

    $page->script(<<<'JS'
        (() => {
            const viewer = document.querySelector('[data-source-asset-viewer]');
            const viewport = document.querySelector('[data-source-asset-image-viewport]');
            const state = Alpine.$data(viewer);
            const rect = viewport.getBoundingClientRect();
            const centerX = rect.left + (rect.width / 2);
            const centerY = rect.top + (rect.height / 2);

            state.fit();

            let singleTouchPreventedAtFit = false;
            state.touchStart({
                touches: [{ clientX: centerX, clientY: centerY }],
                preventDefault() { singleTouchPreventedAtFit = true; },
            });

            let pinchStartPrevented = false;
            state.touchStart({
                touches: [
                    { clientX: centerX - 50, clientY: centerY },
                    { clientX: centerX + 50, clientY: centerY },
                ],
                preventDefault() { pinchStartPrevented = true; },
            });
            const beforePinch = state.scale;

            let pinchMovePrevented = false;
            state.touchMove({
                touches: [
                    { clientX: centerX - 100, clientY: centerY },
                    { clientX: centerX + 100, clientY: centerY },
                ],
                preventDefault() { pinchMovePrevented = true; },
            });

            window.__sourceImageViewerTouch = {
                singleTouchPreventedAtFit,
                pinchStartPrevented,
                pinchMovePrevented,
                beforePinch,
                afterPinch: state.scale,
            };
        })()
        JS);

    $page
        ->assertScript('window.__sourceImageViewerTouch.singleTouchPreventedAtFit', false)
        ->assertScript('window.__sourceImageViewerTouch.pinchStartPrevented', true)
        ->assertScript('window.__sourceImageViewerTouch.pinchMovePrevented', true)
        ->assertScript('window.__sourceImageViewerTouch.afterPinch > window.__sourceImageViewerTouch.beforePinch', true)
        ->assertAttribute('[data-source-asset-image-viewport]', 'data-source-asset-wheel-zoom', 'ctrl-or-meta');
});
