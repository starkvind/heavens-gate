<?php if ($numero_temporada <= 50) { ?>

	<?php
	if (function_exists('hg_page_register_stylesheet')) {
		hg_page_register_stylesheet('/assets/css/hg-archive-panel.css');
	}
	?>
	<script src="/assets/vendor/chartjs/chart.min.js"></script>

		<fieldset class="hg-archive-panel">
			<legend class="hg-archive-panel__legend">&nbsp;Participación en la temporada&nbsp;</legend>
			<canvas id="graficoTemporada" width="500" height="200"></canvas>
		</fieldset>

	<script>
		const ctx = document.getElementById('graficoTemporada').getContext('2d');
		const chart = new Chart(ctx, {
			type: 'bar',
			data: {
				labels: <?= json_encode($nombres) ?>,
				datasets: [{
					label: 'Capítulos jugados',
					data: <?= json_encode($jugados) ?>,
					backgroundColor: 'rgba(75, 192, 192, 0.6)',
					borderColor: 'rgba(75, 192, 192, 1)',
					borderWidth: 1
				}]
			},
			options: {
				indexAxis: 'y',
				responsive: true,
				plugins: {
					tooltip: {
						callbacks: {
							label: function(context) {
								const value = context.parsed.x;
								const total = <?= $total_capitulos ?>;
								const porcentaje = <?= json_encode($porcentajes) ?>[context.dataIndex];
								return `${value} de ${total} capítulos (${porcentaje}%)`;
							}
						}
					},
					legend: {
						display: false
					}
				},
				scales: {
					x: {
						beginAtZero: true,
						max: <?= $total_capitulos ?>,
						ticks: { stepSize: 1, color: 'white' }
					},
					y: {
						ticks: { color: 'white' }
					}
				}
			}
		});
	</script>

<?php } ?>
