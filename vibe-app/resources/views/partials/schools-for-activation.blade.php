            <section class="mb-8 overflow-hidden rounded border border-outline-variant/30 bg-white">
                <div class="flex flex-col justify-between gap-3 bg-primary px-5 py-4 text-white sm:flex-row sm:items-center">
                    <div><h2 class="text-lg font-semibold">Schools for Activation</h2><p class="mt-1 text-xs text-white/75">Approve pending school registrations from here.</p></div>
                    <span class="rounded bg-white/15 px-3 py-1 text-xs font-semibold">{{ $pendingPreRegistrations->count() }} Pending</span>
                </div>
                <div class="divide-y divide-outline-variant/20">
                    @forelse($pendingPreRegistrations as $pendingSchool)
                        @php $pendingUser = $pendingSchool->users->first(); @endphp
                        <div class="grid gap-4 px-5 py-4 md:grid-cols-[1fr_auto] md:items-center">
                            <div>
                                <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold">{{ $pendingSchool->name }}</h3><span class="rounded bg-error/10 px-2 py-1 text-[11px] font-semibold text-error">Needs activation</span></div>
                                <p class="mt-1 text-xs text-on-surface-variant">{{ $pendingSchool->code }} · {{ $pendingSchool->division ?: 'Division not set' }} · {{ $pendingSchool->address ?: 'Address not set' }}</p>
                                <p class="mt-2 text-xs text-on-surface-variant">Initial admin: <span class="font-semibold text-on-surface">{{ $pendingUser?->name ?? 'Not set' }}</span> {{ $pendingUser?->email ? '('.$pendingUser->email.')' : '' }}</p>
                            </div>
                            <div class="flex flex-wrap gap-2 md:justify-end">
                                <a href="{{ route('school-settings', ['ui' => 'staff-save-v7', 'school_id' => $pendingSchool->id]) }}" class="rounded border border-outline-variant/60 px-4 py-2.5 text-xs font-semibold hover:bg-primary hover:text-white">Review Details</a>
                                <form method="POST" action="{{ route('school-settings.school.approve', $pendingSchool) }}">@csrf<input type="hidden" name="redirect_to" value="subscriptions"><button type="submit" class="rounded bg-secondary px-4 py-2.5 text-xs font-semibold text-white hover:bg-primary">Activate School</button></form>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-on-surface-variant">No schools are waiting for activation.</div>
                    @endforelse
                </div>
            </section>
