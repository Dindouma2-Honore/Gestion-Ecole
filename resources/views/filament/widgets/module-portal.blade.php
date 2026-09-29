<x-filament-widgets::widget>
    <section id="module-coverflow" class="module-coverflow" aria-labelledby="module-coverflow-title"
        x-data="{
            active: 0,
            total: {{ count($modules) }},
            dragStart: 0,
            dragCurrent: 0,
            isDragging: false,
            dragOffset: 0,
            loadingModule: null,

            offset(index) {
                let value = index - this.active - this.dragOffset;
                if (value > this.total / 2) value -= this.total;
                if (value < -this.total / 2) value += this.total;
                return value;
            },

            style(index) {
                const offset = this.offset(index);
                const distance = Math.abs(offset);
                const tilt = Math.min(68, 42 * Math.pow(distance, .58)) * Math.sign(offset);
                const scale = distance < 0.5 ? 1 + (0.5 - distance) * 0.08 : Math.max(0.72, 1 - distance * 0.06);
                const translateY = distance < 0.5 ? -14 * (0.5 - distance) : 0;
                const opacity = Math.max(.12, 1 - distance * .13);
                const zIndex = Math.round(100 - distance * 10);
                
                return `transform: translateX(calc(-50% + var(--coverflow-step) * ${offset.toFixed(3)})) translateZ(${-105 * Math.pow(distance, .58)}px) translateY(${translateY.toFixed(1)}px) rotateY(${-tilt.toFixed(1)}deg) scale(${scale.toFixed(3)}); opacity: ${opacity.toFixed(2)}; z-index: ${zIndex};`;
            },

            go(index) {
                this.active = (index + this.total) % this.total;
                this.dragOffset = 0;
            },
            next() { this.go(this.active + 1); },
            previous() { this.go(this.active - 1); },

            beginDrag(event) {
                this.isDragging = true;
                this.dragStart = event.clientX || (event.touches && event.touches[0].clientX) || 0;
                this.dragCurrent = this.dragStart;
                this.dragOffset = 0;
            },

            onDrag(event) {
                if (!this.isDragging) return;
                const currentX = event.clientX || (event.touches && event.touches[0].clientX) || this.dragCurrent;
                const deltaX = currentX - this.dragStart;
                const stepWidth = 220;
                this.dragOffset = - (deltaX / stepWidth);
            },

            endDrag() {
                if (!this.isDragging) return;
                this.isDragging = false;
                if (Math.abs(this.dragOffset) > 0.22) {
                    if (this.dragOffset > 0) {
                        this.next();
                    } else {
                        this.previous();
                    }
                } else {
                    this.dragOffset = 0;
                }
            },

            wheel(event) {
                if (Math.abs(event.deltaY) > 20 || Math.abs(event.deltaX) > 20) {
                    if (event.deltaY > 0 || event.deltaX > 0) {
                        this.next();
                    } else {
                        this.previous();
                    }
                }
            }
        }"
        x-on:keydown.left.prevent="previous()"
        x-on:keydown.right.prevent="next()">

        <header class="module-coverflow__brand">
            <img src="{{ asset('images/logo.png') }}" alt="Logo Ambassadors Educational Complex">
            <div>
                <p>Ambassadors Educational Complex</p>
                <h2 id="module-coverflow-title">Choisissez votre espace de travail</h2>
                <span>Foi · Vision · Discipline</span>
            </div>
        </header>

        <div class="module-coverflow__viewport"
            x-bind:class="{ 'is-dragging': isDragging }"
            role="region" aria-roledescription="carrousel" aria-label="Modules de gestion"
            tabindex="0"
            x-on:pointerdown="beginDrag($event)"
            x-on:pointermove="onDrag($event)"
            x-on:pointerup="endDrag($event)"
            x-on:pointercancel="endDrag()"
            x-on:wheel.passive.throttle.300ms="wheel($event)">
            <div class="module-coverflow__stage">
                @foreach ($modules as $index => $module)
                    <a href="{{ $module['url'] }}"
                        class="module-coverflow__card module-coverflow__card--{{ $module['color'] }}"
                        x-bind:class="{ 'is-active': active === {{ $index }} }"
                        x-bind:style="style({{ $index }})"
                        x-on:click="if (active !== {{ $index }}) { $event.preventDefault(); go({{ $index }}); } else { loadingModule = {{ $index }}; }"
                        role="group" aria-roledescription="diapositive"
                        aria-label="{{ $index + 1 }} sur {{ count($modules) }} : {{ $module['name'] }}"
                        x-bind:aria-current="active === {{ $index }} ? 'true' : 'false'">
                        <span class="module-coverflow__campus" aria-hidden="true"></span>
                        <span class="module-coverflow__glow" aria-hidden="true"></span>
                        <span class="module-coverflow__content">
                            <span class="module-coverflow__status"><i></i>{{ $module['active'] ? 'Disponible' : 'En préparation' }}</span>
                            <span class="module-coverflow__icon"><x-filament::icon :icon="$module['icon']" /></span>
                            <span class="module-coverflow__number">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                            <strong>{{ $module['name'] }}</strong>
                            <small>{{ $module['description'] }}</small>
                            <span class="module-coverflow__action">
                                <span x-show="loadingModule !== {{ $index }}" class="inline-flex items-center gap-1">
                                    Ouvrir le module <x-filament::icon icon="heroicon-m-arrow-right" />
                                </span>
                                <span x-show="loadingModule === {{ $index }}" x-cloak class="inline-flex items-center gap-1.5 font-semibold text-white">
                                    <x-message-loading size="18" class="text-white" /> Ouverture...
                                </span>
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>

        <button type="button" class="module-coverflow__arrow module-coverflow__arrow--left" x-on:click="previous()" aria-label="Module précédent">
            <x-filament::icon icon="heroicon-o-chevron-left" />
        </button>
        <button type="button" class="module-coverflow__arrow module-coverflow__arrow--right" x-on:click="next()" aria-label="Module suivant">
            <x-filament::icon icon="heroicon-o-chevron-right" />
        </button>

        <div class="module-coverflow__caption" aria-live="polite">
            @foreach ($modules as $index => $module)
                <div x-cloak x-show="active === {{ $index }}" x-transition:enter="transition ease-out duration-300 transform" x-transition:enter-start="opacity-0 translate-y-3" x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150 transform" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-2">
                    <strong>{{ $module['name'] }}</strong>
                    <span>{{ $module['description'] }}</span>
                </div>
            @endforeach
        </div>
        <nav class="module-coverflow__dots" aria-label="Choisir un module">
            @foreach ($modules as $index => $module)
                <button type="button" x-on:click="go({{ $index }})" x-bind:class="{ 'is-active': active === {{ $index }} }" aria-label="Afficher {{ $module['name'] }}"></button>
            @endforeach
        </nav>
    </section>
</x-filament-widgets::widget>
