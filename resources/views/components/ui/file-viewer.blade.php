<div wire:ignore>
    <div
        id="file-viewer-root"
        x-data="{
            viewerOpen: false,
            activeIndex: 0,
            items: [],
            touchStartX: 0,
            openViewer(detail) {
                this.items = detail.items ?? [];
                this.activeIndex = detail.index ?? 0;
                this.viewerOpen = this.items.length > 0;
            },
            closeViewer() {
                this.viewerOpen = false;
            },
            syncViewer(detail) {
                this.items = detail.items ?? [];
                if (this.items.length === 0) {
                    this.closeViewer();
                    return;
                }
                if (this.activeIndex >= this.items.length) {
                    this.activeIndex = this.items.length - 1;
                }
            },
            activeItem() {
                return this.items[this.activeIndex] ?? null;
            },
            isImage(item) {
                return item?.kind === 'image' || (item?.mime ?? '').startsWith('image/');
            },
            isPdf(item) {
                return item?.kind === 'pdf' || item?.mime === 'application/pdf';
            },
            nextItem() {
                if (this.items.length === 0) return;
                this.activeIndex = (this.activeIndex + 1) % this.items.length;
            },
            prevItem() {
                if (this.items.length === 0) return;
                this.activeIndex = (this.activeIndex - 1 + this.items.length) % this.items.length;
            },
            handleTouchStart(event) {
                this.touchStartX = event.changedTouches[0].screenX;
            },
            handleTouchEnd(event) {
                if (this.items.length <= 1) return;
                const diff = event.changedTouches[0].screenX - this.touchStartX;
                if (diff > 50) this.prevItem();
                if (diff < -50) this.nextItem();
            },
        }"
        @open-file-viewer.window="openViewer($event.detail)"
        @file-viewer-sync.window="syncViewer($event.detail)"
        @keydown.escape.window="viewerOpen && closeViewer()"
        @keydown.arrow-right.window="viewerOpen && nextItem()"
        @keydown.arrow-left.window="viewerOpen && prevItem()"
    >
        <div
            x-show="viewerOpen"
            x-cloak
            class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="activeItem()?.label || @js(__('file_viewer.title'))"
        >
            <div
                class="absolute inset-0 bg-black/80"
                @click="closeViewer()"
                aria-hidden="true"
            ></div>

            <div class="relative z-10 flex w-full max-w-7xl flex-col gap-3" @click.stop>
                <div class="flex items-center justify-end gap-3 text-white">
                    <span x-show="items.length > 1" class="mr-auto text-xs text-white/80">
                        <span x-text="activeIndex + 1"></span>/<span x-text="items.length"></span>
                    </span>
                    <a
                        x-show="activeItem()?.downloadUrl && ! isPdf(activeItem())"
                        :href="activeItem()?.downloadUrl"
                        class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-white/30 bg-white/10 px-4 py-2 text-sm font-medium text-white transition hover:bg-white/20"
                        download
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"/>
                        </svg>
                        {{ __('file_viewer.download') }}
                    </a>
                    <button
                        type="button"
                        @click="closeViewer()"
                        class="shrink-0 rounded-lg p-2 text-white/80 transition hover:bg-white/10 hover:text-white"
                        :aria-label="@js(__('file_viewer.close'))"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div
                    class="relative flex min-h-[44vh] items-center justify-center rounded-xl border border-white/10 bg-black/40 p-2 sm:min-h-[66vh]"
                    @touchstart="handleTouchStart($event)"
                    @touchend="handleTouchEnd($event)"
                >
                    <button
                        type="button"
                        @click.stop="prevItem()"
                        class="absolute left-2 z-10 hidden rounded-full bg-black/50 p-3 text-white transition hover:bg-black/70 sm:block"
                        :aria-label="@js(__('file_viewer.previous'))"
                        x-show="items.length > 1"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    <div class="flex w-full items-center justify-center px-2 sm:px-14">
                        <template x-for="(item, index) in items" :key="item.viewUrl + '-' + index">
                            <div x-show="activeIndex === index" class="flex w-full justify-center">
                                <img
                                    x-show="isImage(item)"
                                    :src="item.viewUrl"
                                    :alt="item.label"
                                    draggable="false"
                                    class="pointer-events-none block max-h-[55vh] w-auto max-w-full select-none rounded-lg border border-white/20 bg-black object-contain shadow-2xl sm:max-h-[77vh]"
                                >
                                <iframe
                                    x-show="isPdf(item)"
                                    :src="item.viewUrl"
                                    :title="item.label"
                                    class="h-[55vh] w-full rounded-lg bg-white shadow-2xl sm:h-[77vh]"
                                ></iframe>
                                <div
                                    x-show="!isImage(item) && !isPdf(item)"
                                    class="max-w-md rounded-xl border border-white/20 bg-slate-900/80 px-6 py-8 text-center text-white"
                                >
                                    <p class="text-sm text-white/80">{{ __('file_viewer.unsupported_preview') }}</p>
                                    <a
                                        :href="item.downloadUrl"
                                        class="mt-4 inline-flex items-center justify-center rounded-lg bg-white px-4 py-2 text-sm font-medium text-slate-900 transition hover:bg-slate-100"
                                        download
                                    >
                                        {{ __('file_viewer.download') }}
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button
                        type="button"
                        @click.stop="nextItem()"
                        class="absolute right-2 z-10 hidden rounded-full bg-black/50 p-3 text-white transition hover:bg-black/70 sm:block"
                        :aria-label="@js(__('file_viewer.next'))"
                        x-show="items.length > 1"
                    >
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
