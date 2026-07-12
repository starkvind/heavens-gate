(function (global) {
    'use strict';

    var app = global.HGCardGame = global.HGCardGame || {};
    app.memory = app.memory || {};

    var config = {
        getState: function () { return null; },
        getEls: function () { return null; },
        activeWorkEntries: function () { return []; },
        workCandidateEntries: function () { return []; },
        totalWorkClaimable: function () { return 0; },
        workCanStop: function () { return false; },
        workRemainingLabel: function () { return ''; },
        assignCopyToWorkById: function () { return false; },
        stopCopyWork: function () { return false; },
        renderCard: function () { return global.document.createElement('div'); },
        escapeHtml: function (value) { return String(value || ''); },
        uiText: function (key, fallback, values) {
            var text = fallback || key;
            return values ? text.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
                return Object.prototype.hasOwnProperty.call(values, name) ? String(values[name]) : match;
            }) : text;
        },
        cardGameIconHtml: function () { return ''; },
        isMemoryContext: function () { return true; },
        workMaxAssignments: function () { return 0; }
    };

    function configure(nextConfig) {
        if (!nextConfig || typeof nextConfig !== 'object') { return api; }
        Object.keys(nextConfig).forEach(function (key) {
            config[key] = nextConfig[key];
        });
        return api;
    }

    function renderWorkBench() {
        var state = config.getState();
        var els = config.getEls();
        if (!els.workSummary && !els.workList && !els.workClaimBtn) { return; }
        if (state.mobile && !config.isMemoryContext()) { return; }
        var entries = config.activeWorkEntries();
        var candidates = config.workCandidateEntries();
        var totalRate = entries.reduce(function (sum, entry) { return sum + entry.rate; }, 0);
        var claimable = config.totalWorkClaimable(entries);
        if (els.workSummary) {
            els.workSummary.innerHTML = [
                '<span><strong>' + entries.length + ' / ' + config.workMaxAssignments() + '</strong><small>' + config.escapeHtml(config.uiText('memory.summary_active', 'rememorando')) + '</small></span>',
                '<span><strong>' + totalRate.toFixed(1) + '</strong><small>' + config.escapeHtml(config.uiText('memory.summary_rate', 'Mn/min')) + '</small></span>',
                '<span><strong>' + claimable + '</strong><small>' + config.escapeHtml(config.uiText('memory.summary_claimable', 'reclamables')) + '</small></span>'
            ].join('');
        }
        if (els.workClaimBtn) {
            els.workClaimBtn.disabled = claimable <= 0;
            els.workClaimBtn.textContent = claimable > 0
                ? config.uiText('memory.claim_button_amount', 'Reclamar +{amount}', { amount: claimable })
                : config.uiText('memory.claim_button', 'Reclamar');
        }
        if (!els.workList) { return; }
        els.workList.innerHTML = '';
        for (var slotIndex = 0; slotIndex < config.workMaxAssignments(); slotIndex++) {
            var entry = entries[slotIndex] || null;
            var slot = document.createElement('article');
            slot.className = 'hg-work-slot' + (entry ? ' hg-collection-row--' + entry.rarity : ' is-empty');
            if (!entry) {
                slot.innerHTML =
                    '<div class="hg-work-slot__empty">' +
                        '<strong>' + config.escapeHtml(config.uiText('memory.slot_label', 'Hueco {slot}', { slot: slotIndex + 1 })) + '</strong>' +
                        '<span>' + config.escapeHtml(config.uiText('memory.empty_slot_text', 'Elige una carta para recordar.')) + '</span>' +
                    '</div>';
                if (candidates.length) {
                    var select = document.createElement('select');
                    select.setAttribute('aria-label', config.uiText('memory.select_label', 'Carta para recordar en hueco {slot}', { slot: slotIndex + 1 }));
                    candidates.forEach(function (candidate) {
                        var option = document.createElement('option');
                        option.value = String(candidate.copy.instanceId || '');
                        option.textContent = candidate.card.card_name + ' · ' + candidate.rate.toFixed(1) + ' Mn/min';
                        select.appendChild(option);
                    });
                    var add = document.createElement('button');
                    add.type = 'button';
                    add.className = 'hg-icon-action hg-icon-action--memory';
                    add.title = config.uiText('memory.remember_action', 'Recordar');
                    add.setAttribute('aria-label', config.uiText('memory.remember_selected_label', 'Recordar carta seleccionada'));
                    add.innerHTML = config.cardGameIconHtml('remembrance', config.uiText('memory.remember_action', 'Recordar'));
                    (function (selectNode) {
                        add.addEventListener('click', function () {
                            config.assignCopyToWorkById(selectNode.value);
                        });
                    })(select);
                    slot.appendChild(select);
                    slot.appendChild(add);
                } else {
                    var none = document.createElement('p');
                    none.className = 'hg-empty-state';
                    none.textContent = config.uiText('memory.no_cards', 'No hay cartas disponibles.');
                    slot.appendChild(none);
                }
                els.workList.appendChild(slot);
                continue;
            }
            var canStop = config.workCanStop(entry);
            var cardWrap = document.createElement('div');
            cardWrap.className = 'hg-work-slot__card';
            var memoryCard = config.renderCard(entry.baseCard, entry.copy, { noLink: true, memoryCompact: true });
            memoryCard.className += ' hg-card--memory';
            cardWrap.appendChild(memoryCard);
            var effects = document.createElement('div');
            effects.className = 'hg-work-slot__effects';
            effects.innerHTML =
                '<b>' + entry.rate.toFixed(1) + ' Mnemones/min</b>' +
                '<span>' + config.escapeHtml(config.uiText('memory.gains', 'Ganancias: +{amount}', { amount: entry.claimable })) + '</span>' +
                '<small>' + config.escapeHtml(canStop ? config.uiText('memory.can_return', 'Puede volver') : config.uiText('memory.returns_in', 'Vuelve en {time}', { time: config.workRemainingLabel(entry) })) + '</small>';
            var stop = document.createElement('button');
            stop.type = 'button';
            stop.textContent = config.uiText('memory.stop', 'Retirar');
            stop.disabled = !canStop;
            stop.title = canStop ? '' : config.uiText('memory.min_duration_title', 'Debe rememorar al menos 24 horas.');
            (function (entryForStop) {
                stop.addEventListener('click', function () {
                    config.stopCopyWork(entryForStop.copy.instanceId);
                });
            })(entry);
            effects.appendChild(stop);
            slot.appendChild(cardWrap);
            slot.appendChild(effects);
            els.workList.appendChild(slot);
        }
    }

    var api = Object.freeze({
        configure: configure,
        renderWorkBench: renderWorkBench
    });

    app.memory.render = api;
})(window);
