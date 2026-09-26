<?php
?>

<article class="hg-mobile-bio">
    <nav class="hg-mobile-local-nav">
        <a href="/characters?view=mobile">Volver a personajes</a>
    </nav>

    <header class="hg-mobile-bio-hero">
        <?php if ($avatar !== ''): ?>
            <img src="<?= hg_mobile_bio_h($avatar) ?>" alt="">
        <?php endif; ?>
        <div>
            <p class="hg-mobile-kicker"><?= hg_mobile_bio_h($character['type_name'] ?? '') ?></p>
            <h1><?= hg_mobile_bio_h($character['name'] ?? '') ?></h1>
            <?php if (!empty($character['alias'])): ?>
                <p><?= hg_mobile_bio_h($character['alias']) ?></p>
            <?php endif; ?>
        </div>
    </header>

    <section class="hg-mobile-section">
        <div class="hg-mobile-fact-grid">
            <div><span>Concepto</span><strong><?= hg_mobile_bio_h($character['concept'] ?? '') ?></strong></div>
            <div><span>Sistema</span><strong><?= hg_mobile_bio_h($character['system_name'] ?? '') ?></strong></div>
            <?php if ($status !== ''): ?>
                <div><span>Estado</span><strong><?= hg_mobile_bio_h($status) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($character['rank'])): ?>
                <div><span>Rango</span><strong><?= hg_mobile_bio_h($character['rank']) ?></strong></div>
            <?php endif; ?>
            <?php if (!empty($character['garou_name'])): ?>
                <div><span>Nombre Garou</span><strong><?= hg_mobile_bio_h($character['garou_name']) ?></strong></div>
            <?php endif; ?>
            <?php foreach ($detailLinks as $label => $html): ?>
                <?php if (trim(strip_tags((string)$html)) === '') { continue; } ?>
                <div><span><?= hg_mobile_bio_h($label) ?></span><strong><?= $html ?></strong></div>
            <?php endforeach; ?>
            <div><span>ID</span><strong><?= $characterId ?></strong></div>
        </div>
    </section>

    <?php if ($deathDescription !== ''): ?>
        <section class="hg-mobile-section">
            <h2>Muerte</h2>
            <p><?= hg_mobile_bio_h($deathDescription) ?></p>
        </section>
    <?php endif; ?>

    <?php if (trim(strip_tags($infoHtml)) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose">
            <h2>Información</h2>
            <?= $infoHtml ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($relations)): ?>
        <section class="hg-mobile-section">
            <h2>Relaciones</h2>
            <div class="hg-mobile-list hg-mobile-linked-list">
                <?php foreach ($relations as $relation): ?>
                    <?php
                        $otherId = (int)($relation['other_id'] ?? 0);
                        $otherName = trim((string)($relation['other_name'] ?? ''));
                        $otherAlias = trim((string)($relation['other_alias'] ?? ''));
                        $relationType = trim((string)($relation['relation_type'] ?? 'Relación'));
                        $relationTag = trim((string)($relation['tag'] ?? ''));
                        $relationDesc = trim((string)($relation['description'] ?? ''));
                        $direction = (string)($relation['direction'] ?? '') === 'incoming' ? 'Entrante' : 'Saliente';
                        $otherHref = hg_mobile_bio_pretty_href($link, 'fact_characters', '/characters', $otherId);
                    ?>
                    <?php
                        $otherAvatar = hg_character_avatar_url(
                            (string)($relation['image_url'] ?? ''),
                            (string)($relation['gender'] ?? '')
                        );
                        $otherLabel = $otherName !== '' ? $otherName : ('Personaje #' . $otherId);
                    ?>
                    <div class="hg-mobile-relation-card">
                        <a class="hg-mobile-relation-avatar" href="<?= hg_mobile_bio_h($otherHref) ?>" aria-label="<?= hg_mobile_bio_h($otherLabel) ?>">
                            <img src="<?= hg_mobile_bio_h($otherAvatar) ?>" alt="" width="44" height="44" loading="lazy">
                        </a>
                        <div class="hg-mobile-relation-copy">
                            <strong><?= hg_mobile_bio_link($otherHref, $otherLabel) ?></strong>
                            <span><?= hg_mobile_bio_h($relationType) ?> · <?= hg_mobile_bio_h($direction) ?><?= $relationTag !== '' ? ' · ' . hg_mobile_bio_h($relationTag) : '' ?><?= $otherAlias !== '' ? ' · ' . hg_mobile_bio_h($otherAlias) : '' ?></span>
                            <?php if ($relationDesc !== ''): ?>
                                <p><?= nl2br(hg_mobile_bio_h($relationDesc)) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($traitsByKind)): ?>
        <section class="hg-mobile-section">
            <h2>Rasgos</h2>
            <?php foreach ($traitsByKind as $kind => $traits): ?>
                <details class="hg-mobile-details" open>
                    <summary><?= hg_mobile_bio_h($kind) ?></summary>
                    <div class="hg-mobile-trait-list">
                        <?php foreach ($traits as $trait): ?>
                            <div>
                                <span><?= hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, 'dim_traits', '/rules/traits', (int)($trait['id'] ?? 0)), (string)($trait['name'] ?? '')) ?></span>
                                <strong data-hg-mobile-form-trait="<?= (int)($trait['id'] ?? 0) ?>" data-hg-mobile-form-base="<?= (int)($trait['value'] ?? 0) ?>"><span data-hg-mobile-form-value><?= (int)($trait['value'] ?? 0) ?></span> <span data-hg-mobile-form-dots><?= hg_mobile_bio_trait_dots((int)($trait['value'] ?? 0)) ?></span></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($mobileActions)): ?>
        <section class="hg-mobile-section hg-mobile-actions" data-mobile-actions>
            <details class="hg-mobile-details hg-mobile-actions__section" open>
                <summary>Acciones <span class="hg-mobile-actions__count"><?= count($mobileActions) ?></span></summary>
                <div class="hg-mobile-actions__content">
                    <label class="hg-mobile-actions__search">
                        <span>Buscar acciones</span>
                        <input type="search" data-mobile-action-search placeholder="Nombre, categoría, atributo o habilidad" autocomplete="off">
                    </label>
                    <p class="hg-mobile-list-empty" data-mobile-action-empty hidden>No hay acciones que coincidan.</p>
                    <?php foreach ($mobileActionsByCategory as $category => $categoryActions): ?>
                        <details class="hg-mobile-details hg-mobile-actions__category" data-mobile-action-category open>
                            <summary><?= hg_mobile_bio_h($category) ?> <span class="hg-mobile-actions__count"><?= count($categoryActions) ?></span></summary>
                            <div class="hg-mobile-list">
                                <?php foreach ($categoryActions as $action): ?>
                                    <?php
                                        $difficultyLabel = (string)($action['difficulty_mode'] ?? '') === 'fixed'
                                            ? 'Dificultad ' . (int)($action['fixed_difficulty'] ?? 6)
                                            : 'Dificultad variable (base ' . (int)($action['suggested_difficulty'] ?? 6) . ')';
                                        $dice = (int)($action['attribute_value'] ?? 0) + (int)($action['skill_value'] ?? 0);
                                        $searchText = implode(' ', [
                                            (string)($action['name'] ?? ''), (string)$category, (string)($action['text'] ?? ''),
                                            (string)($action['attribute_name'] ?? ''), (string)($action['skill_name'] ?? ''),
                                        ]);
                                    ?>
                                    <div data-mobile-action-card data-mobile-action-search-text="<?= hg_mobile_bio_h($searchText) ?>">
                                        <strong><img src="/img/ui/icons/icon_book.webp" alt="" width="20" height="20" loading="lazy"> <?= hg_mobile_bio_link((string)($action['href'] ?? ''), (string)($action['name'] ?? 'Acción')) ?></strong>
                                        <span><?= hg_mobile_bio_h((string)($action['attribute_name'] ?? '')) ?> + <?= hg_mobile_bio_h((string)($action['skill_name'] ?? '')) ?> · <?= $dice ?> dados · <?= hg_mobile_bio_h($difficultyLabel) ?></span>
                                        <a class="boton2" href="<?= hg_mobile_bio_h((string)($action['roll_href'] ?? '/tools/dice')) ?>">Tirar</a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endforeach; ?>
                </div>
            </details>
        </section>
        
    <?php endif; ?>    <?php if ($hasCharacterSheet && array_filter($mobileResourcesByKind)): ?>
        <section class="hg-mobile-section">
            <h2>Recursos</h2>
            <?php foreach (['renombre' => 'Renombre', 'estado' => 'Estado', 'exp' => 'Experiencia'] as $kind => $title): ?>
                <?php $resources = $mobileResourcesByKind[$kind] ?? []; ?>
                <?php if (empty($resources)) { continue; } ?>
                <details class="hg-mobile-details" open>
                    <summary><?= hg_mobile_bio_h($title) ?></summary>
                    <div class="hg-mobile-list">
                        <?php foreach ($resources as $resource): ?>
                            <?php
                                $perm = (int)($resource['perm'] ?? 0);
                                $temp = (int)($resource['temp'] ?? 0);
                                $dots = static fn(int $value): string => html_entity_decode(
                                    strip_tags(hg_mobile_bio_trait_dots($value)),
                                    ENT_QUOTES | ENT_HTML5,
                                    'UTF-8'
                                );
                                $value = $kind === 'renombre'
                                    ? 'P ' . $perm . ' ' . $dots($perm) . ' / T ' . $temp . ' ' . $dots($temp)
                                    : ($kind === 'exp'
                                        ? $temp . ' / ' . $perm . ' PX'
                                        : 'T ' . $temp . ' / P ' . $perm . ' ' . $dots($temp));
                            ?>
                            <div>
                                <strong><?= hg_mobile_bio_h($resource['name'] ?? '') ?></strong>
                                <span><?= hg_mobile_bio_h($value) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($merits)): ?>
        <section class="hg-mobile-section">
            <h2>Méritos y defectos</h2>
            <div class="hg-mobile-list">
                <?php foreach ($merits as $merit): ?>
                    <div>
                        <strong><?= hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, 'dim_merits_flaws', '/rules/merits-flaws', (int)($merit['id'] ?? 0)), (string)($merit['name'] ?? '')) ?></strong>
                        <span><?= hg_mobile_bio_h($merit['kind'] ?? '') ?><?= isset($merit['level']) && $merit['level'] !== null ? ' ' . (int)$merit['level'] : '' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($conditions)): ?>
        <section class="hg-mobile-section">
            <h2>Condiciones</h2>
            <div class="hg-mobile-list">
                <?php foreach ($conditions as $condition): ?>
                    <div>
                        <strong><?= hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, 'dim_character_conditions', '/rules/conditions', (int)($condition['id'] ?? 0)), (string)($condition['name'] ?? '')) ?></strong>
                        <span><?= hg_mobile_bio_h($condition['category'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($mobileForms)): ?>
        <?php
            $mobileFormsJson = json_encode($mobileForms, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $mobileBaseManeuversJson = json_encode($mobileBaseManeuvers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        <section class="hg-mobile-section hg-mobile-forms"
                 data-hg-mobile-forms='<?= hg_mobile_bio_h((string)$mobileFormsJson) ?>'
                 data-hg-mobile-base-maneuvers='<?= hg_mobile_bio_h((string)$mobileBaseManeuversJson) ?>'>
            <h2>Formas</h2>
            <label class="hg-mobile-form-control">Forma activa
                <select data-hg-mobile-form-select>
                    <option value="">Forma base</option>
                    <?php foreach ($mobileForms as $form): ?>
                        <option value="<?= (int)$form['id'] ?>"><?= hg_mobile_bio_h($form['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <p data-hg-mobile-form-summary>Forma base: atributos originales.</p>
        </section>
        
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($mobileForms)): ?>
        <?php $mobileBaseManeuversJson = json_encode($mobileBaseManeuvers, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
        <section class="hg-mobile-section hg-mobile-maneuvers" data-hg-mobile-base-maneuvers='<?= hg_mobile_bio_h((string)$mobileBaseManeuversJson) ?>'>
            <h2>Maniobras</h2>
            <div class="hg-mobile-list" data-hg-mobile-maneuver-list></div>
        </section>
        
    <?php endif; ?>

    <?php if ($hasCharacterSheet): ?>
    <?php foreach ($powers as $powerTitle => $powerRows): ?>
        <?php if (empty($powerRows)) { continue; } ?>
        <section class="hg-mobile-section">
            <h2><?= hg_mobile_bio_h($powerTitle) ?></h2>
            <div class="hg-mobile-list">
                <?php foreach ($powerRows as $power): ?>
                    <?php
                        $powerRoutes = [
                            'Dones' => ['fact_gifts', '/powers/gift'],
                            'Disciplinas' => ['dim_discipline_types', '/powers/discipline/type'],
                            'Rituales' => ['fact_rites', '/powers/rite'],
                        ];
                        $powerRoute = $powerRoutes[$powerTitle] ?? ['fact_rites', '/powers/rite'];
                    ?>
                    <div>
                        <strong><?= hg_mobile_bio_link(hg_mobile_bio_pretty_href($link, $powerRoute[0], $powerRoute[1], (int)($power['id'] ?? 0)), (string)($power['name'] ?? '')) ?></strong>
                        <?php if (isset($power['level']) && $power['level'] !== null && (string)$power['level'] !== ''): ?>
                            <span>Nivel <?= (int)$power['level'] ?></span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($hasCharacterSheet && !empty($items)): ?>
        <section class="hg-mobile-section">
            <h2>Inventario</h2>
            <div class="hg-mobile-list">
                <?php foreach ($items as $item): ?>
                    <?php
                        $itemType = (string)($item['type_pretty'] ?? $item['item_type_id'] ?? '');
                        $itemSlug = function_exists('get_pretty_id') ? (get_pretty_id($link, 'fact_items', (int)($item['id'] ?? 0)) ?: (int)($item['id'] ?? 0)) : (int)($item['id'] ?? 0);
                        $itemHref = '/inventory/' . rawurlencode($itemType) . '/' . rawurlencode((string)$itemSlug);
                    ?>
                    <div>
                        <strong><?= hg_mobile_bio_link($itemHref, (string)($item['name'] ?? '')) ?></strong>
                        <span><?= hg_mobile_bio_h($item['type_name'] ?? '') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($chapterParticipation) || !empty($timelineEvents)): ?>
        <section class="hg-mobile-section">
            <h2>Participación</h2>

            <?php if (!empty($chapterParticipation)): ?>
                <details class="hg-mobile-details" open>
                    <summary>Capítulos</summary>
                    <div class="hg-mobile-list hg-mobile-linked-list">
                        <?php foreach ($chapterParticipation as $chapter): ?>
                            <?php
                                $chapterId = (int)($chapter['id'] ?? 0);
                                $chapterHref = hg_mobile_bio_pretty_href($link, 'dim_chapters', '/chapters', $chapterId);
                                $seasonName = trim((string)($chapter['temporada_name'] ?? ''));
                                $seasonNumber = trim((string)($chapter['season_number'] ?? ''));
                                $seasonLabel = $seasonName !== '' ? $seasonName : ($seasonNumber !== '' ? 'Temporada ' . $seasonNumber : '');
                                $playedDate = hg_mobile_bio_date($chapter['played_date'] ?? '');
                            ?>
                            <div>
                                <strong><?= hg_mobile_bio_link($chapterHref, (string)($chapter['name'] ?? '')) ?></strong>
                                <span><?= hg_mobile_bio_h($seasonLabel) ?><?= $playedDate !== '' ? ' · ' . hg_mobile_bio_h($playedDate) : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (!empty($timelineEvents)): ?>
                <details class="hg-mobile-details" open>
                    <summary>Eventos</summary>
                    <div class="hg-mobile-list hg-mobile-linked-list">
                        <?php foreach ($timelineEvents as $event): ?>
                            <?php
                                $eventId = (int)($event['id'] ?? 0);
                                $eventSlug = trim((string)($event['pretty_id'] ?? ''));
                                $eventHref = '/timeline/event/' . rawurlencode($eventSlug !== '' ? $eventSlug : (string)$eventId);
                                $eventDate = hg_mobile_bio_date($event['event_date'] ?? '');
                            ?>
                            <div>
                                <strong><?= hg_mobile_bio_link($eventHref, (string)($event['title'] ?? 'Evento')) ?></strong>
                                <span><?= hg_mobile_bio_h($event['type_name'] ?? 'Evento') ?><?= $eventDate !== '' ? ' · ' . hg_mobile_bio_h($eventDate) : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($characterDocs) || !empty($characterExternalLinks)): ?>
        <section class="hg-mobile-section">
            <h2>Documentos y enlaces</h2>

            <?php if (!empty($characterDocs)): ?>
                <details class="hg-mobile-details" open>
                    <summary>Documentos internos</summary>
                    <div class="hg-mobile-list hg-mobile-linked-list">
                        <?php foreach ($characterDocs as $doc): ?>
                            <?php
                                $docId = (int)($doc['doc_id'] ?? 0);
                                $docHref = hg_mobile_bio_pretty_href($link, 'fact_docs', '/documents', $docId);
                                $docSection = trim((string)($doc['section_name'] ?? 'Documento'));
                                $docRel = trim((string)($doc['relation_label'] ?? ''));
                            ?>
                            <div>
                                <strong><?= hg_mobile_bio_link($docHref, (string)($doc['title'] ?? ('Documento #' . $docId))) ?></strong>
                                <span><?= hg_mobile_bio_h($docSection !== '' ? $docSection : 'Documento') ?><?= $docRel !== '' ? ' · ' . hg_mobile_bio_h($docRel) : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>

            <?php if (!empty($characterExternalLinks)): ?>
                <details class="hg-mobile-details" open>
                    <summary>Enlaces externos</summary>
                    <div class="hg-mobile-list hg-mobile-linked-list">
                        <?php foreach ($characterExternalLinks as $external): ?>
                            <?php
                                $externalTitle = trim((string)($external['title'] ?? ''));
                                $externalUrl = trim((string)($external['url'] ?? '#'));
                                $externalKind = trim((string)($external['kind'] ?? 'Enlace'));
                                $externalSource = trim((string)($external['source_label'] ?? ''));
                                $externalRel = trim((string)($external['relation_label'] ?? ''));
                                $externalActive = (int)($external['is_active'] ?? 1) === 1;
                            ?>
                            <div>
                                <strong><a href="<?= hg_mobile_bio_h($externalUrl !== '' ? $externalUrl : '#') ?>" target="_blank" rel="noopener noreferrer"><?= hg_mobile_bio_h($externalTitle !== '' ? $externalTitle : $externalUrl) ?></a></strong>
                                <span><?= hg_mobile_bio_h($externalKind !== '' ? $externalKind : 'Enlace') ?><?= $externalSource !== '' ? ' · ' . hg_mobile_bio_h($externalSource) : '' ?><?= $externalRel !== '' ? ' · ' . hg_mobile_bio_h($externalRel) : '' ?><?= !$externalActive ? ' · inactivo' : '' ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($comments)): ?>
        <section class="hg-mobile-section">
            <h2>Comentarios</h2>
            <div class="hg-mobile-list">
                <?php foreach ($comments as $comment): ?>
                    <div>
                        <strong><?= hg_mobile_bio_h($comment['nick'] ?? '') ?></strong>
                        <span><?= hg_mobile_bio_h($comment['commented_at'] ?? '') ?></span>
                        <p><?= nl2br(hg_mobile_bio_h($comment['message'] ?? '')) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
