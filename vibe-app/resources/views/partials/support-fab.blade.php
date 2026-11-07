@unless(request()->routeIs('support'))
<style>body:has(#doc-dock-open) #support-fab{bottom:4.5rem}</style>
<a id="support-fab" href="{{ route('support') }}" class="fixed bottom-4 right-4 z-30 flex h-12 w-12 items-center justify-center rounded-full bg-primary text-white shadow-lg hover:bg-primary-container focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary print:hidden" aria-label="Support" title="Support"><span class="material-symbols-outlined text-[24px]" aria-hidden="true">support_agent</span></a>
@endunless
