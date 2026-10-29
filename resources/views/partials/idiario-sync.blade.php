@php
    $idiarioSync = $idiarioSync ?? ['visible' => false, 'status' => null, 'message' => null, 'percentage' => null, 'icon' => null];
@endphp
<div id="idiario-sync"
     class="idiario-sync -{{ $idiarioSync['status'] ?? 'idle' }}"
     @if(empty($idiarioSync['visible'])) hidden @endif
     data-status-url="{{ route('idiario-sync.status') }}"
     data-dismiss-url="{{ route('idiario-sync.dismiss') }}">
    <button type="button" class="idiario-sync__button" aria-label="Sincronização com o i-Diário">
        <i class="fa {{ $idiarioSync['icon'] ?? 'fa-refresh' }} @if(($idiarioSync['status'] ?? '') === 'syncing') fa-spin @endif" aria-hidden="true"></i>
    </button>
    <div class="idiario-sync__panel" hidden>
        <p class="idiario-sync__message">{{ $idiarioSync['message'] }}</p>
        <p class="idiario-sync__percentage" @if(($idiarioSync['status'] ?? '') !== 'syncing' || $idiarioSync['percentage'] === null) hidden @endif>
            <span class="idiario-sync__percentage-value">{{ $idiarioSync['percentage'] }}</span>%
        </p>
        <button type="button" class="idiario-sync__dismiss" @if(($idiarioSync['status'] ?? '') !== 'failed') hidden @endif>Dispensar</button>
    </div>
</div>
<script>
    (function () {
        var root = document.getElementById('idiario-sync');
        if (!root) {
            return;
        }

        var button = root.querySelector('.idiario-sync__button');
        var panel = root.querySelector('.idiario-sync__panel');
        var message = root.querySelector('.idiario-sync__message');
        var percentage = root.querySelector('.idiario-sync__percentage');
        var percentageValue = root.querySelector('.idiario-sync__percentage-value');
        var dismiss = root.querySelector('.idiario-sync__dismiss');
        var icon = button.querySelector('.fa');
        var pollTimer = null;
        var hideTimer = null;

        function csrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.getAttribute('content') : '';
        }

        function stopPolling() {
            if (pollTimer) {
                clearInterval(pollTimer);
                pollTimer = null;
            }
        }

        function ensurePolling() {
            if (!pollTimer) {
                pollTimer = setInterval(tick, 8000);
            }
        }

        function apply(data) {
            if (!data || !data.visible) {
                root.hidden = true;
                panel.hidden = true;
                stopPolling();
                return;
            }

            root.hidden = false;
            root.className = 'idiario-sync -' + data.status;
            icon.className = 'fa ' + (data.icon || 'fa-refresh') + (data.status === 'syncing' ? ' fa-spin' : '');
            message.textContent = data.message || '';

            if (data.status === 'syncing' && data.percentage !== null && data.percentage !== undefined) {
                percentage.hidden = false;
                percentageValue.textContent = String(data.percentage);
            } else {
                percentage.hidden = true;
            }

            dismiss.hidden = data.status !== 'failed';

            if (data.status === 'queued' || data.status === 'syncing') {
                if (hideTimer) {
                    clearTimeout(hideTimer);
                    hideTimer = null;
                }
                ensurePolling();
            } else if (data.status === 'completed') {
                stopPolling();
                if (!hideTimer) {
                    hideTimer = setTimeout(function () {
                        root.hidden = true;
                        panel.hidden = true;
                    }, 8000);
                }
            } else {
                stopPolling();
            }
        }

        function tick() {
            fetch(root.getAttribute('data-status-url'), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            }).then(function (response) {
                if (!response.ok) {
                    return null;
                }
                return response.json();
            }).then(function (data) {
                if (data) {
                    apply(data);
                }
            }).catch(function () {});
        }

        root.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        button.addEventListener('click', function (event) {
            event.preventDefault();
            panel.hidden = !panel.hidden;
        });

        dismiss.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            fetch(root.getAttribute('data-dismiss-url'), {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(function (response) {
                return response.json();
            }).then(apply).catch(function () {});
        });

        document.addEventListener('click', function () {
            panel.hidden = true;
        });

        panel.addEventListener('click', function (event) {
            event.stopPropagation();
        });

        if (!root.hidden && (root.className.indexOf('-queued') !== -1 || root.className.indexOf('-syncing') !== -1)) {
            ensurePolling();
            tick();
        }
    })();
</script>
