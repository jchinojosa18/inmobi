x-data
x-init="$nextTick(() => {
    const panel = $el.querySelector('[data-modal-panel]');
    const focusable = panel?.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex=\'-1\'])');
    (focusable ?? panel)?.focus();
})"
@keydown.tab="
    const focusables = [...$el.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex=\'-1\'])')].filter(el => el.offsetParent !== null);
    if (focusables.length === 0) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if ($event.shiftKey && document.activeElement === first) { $event.preventDefault(); last.focus(); }
    else if (! $event.shiftKey && document.activeElement === last) { $event.preventDefault(); first.focus(); }
"
