<?php
$activePage = 'home';
$pageTitle = "Home | Jero &amp; Vonn's Six Star Hotel";
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main>
	<section class="royal-hero">
		<div class="royal-hero-overlay"></div>
		<div class="royal-container royal-hero-content">
			<p class="royal-kicker">EST. ANNO DOMINI 2006</p>
			<h1>Jero &amp; Vonn's Royal Chambers</h1>
			<p class="royal-tagline">Rest where legends are made.</p>
			<a class="btn royal-btn" href="reservation.php">Reserve a Chamber</a>
		</div>
	</section>

	<section class="royal-section">
		<div class="royal-container">
			<div class="royal-heading-wrap">
				<h2 class="royal-heading">The Chambers</h2>
			</div>
			<div class="royal-grid">
				<article class="royal-card">
					<div class="royal-image-wrap">
						<img src="assets/img/jellar.jpg" alt="Single guest room">
					</div>
					<div class="royal-card-body">
						<h3>Single Sanctuary</h3>
						<p>A private retreat for the solitary voyager seeking peace within stone walls.</p>
						<p class="royal-price">Starting at $149 <span>/ NIGHT</span></p>
					</div>
				</article>

				<article class="royal-card">
					<div class="royal-image-wrap">
						<img src="assets/img/gian.jpg" alt="Double guest room">
					</div>
					<div class="royal-card-body">
						<h3>Double Sovereign</h3>
						<p>Ample space for two noble guests, featuring hand-carved mahogany furnishings.</p>
						<p class="royal-price">Starting at $229 <span>/ NIGHT</span></p>
					</div>
				</article>

				<article class="royal-card">
					<div class="royal-image-wrap">
						<img src="assets/img/yanyan.jpg" alt="Family suite">
					</div>
					<div class="royal-card-body">
						<h3>Dynasty Suite</h3>
						<p>A grand hall for the whole kin, offering multi-room comfort and warmth.</p>
						<p class="royal-price">Starting at $399 <span>/ NIGHT</span></p>
					</div>
				</article>
			</div>
		</div>
	</section>

	<section class="royal-section royal-section-alt">
		<div class="royal-container royal-table-shell">
			<div class="royal-heading-wrap">
				<h2 class="royal-heading">Royal Rates at a Glance</h2>
				<p class="royal-subhead">Transparent pricing for every lineage.</p>
			</div>
			<div class="royal-table-wrap">
				<table class="royal-table">
					<thead>
						<tr>
							<th>Room Type</th>
							<th>Capacity</th>
							<th class="align-right">Rate / Night</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>Single Sanctuary</td>
							<td>1 Noble</td>
							<td class="align-right">$149.00</td>
						</tr>
						<tr>
							<td>Double Sovereign</td>
							<td>2 Nobles</td>
							<td class="align-right">$229.00</td>
						</tr>
						<tr>
							<td>Dynasty Suite</td>
							<td>4-6 Nobles</td>
							<td class="align-right">$399.00</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<section class="royal-section">
		<div class="royal-container royal-promo-wrap">
			<div class="royal-promo-card">
				<div class="royal-corner top-left"></div>
				<div class="royal-corner top-right"></div>
				<div class="royal-corner bottom-left"></div>
				<div class="royal-corner bottom-right"></div>
				<p class="royal-icon" aria-hidden="true">&#10022;</p>
				<h2 class="royal-heading royal-promo-title">Royal Favour for Cash Payment</h2>
				<div class="royal-discount-row">
					<div>
						<p class="royal-discount-value">10% OFF</p>
						<p class="royal-discount-label">Short Stays</p>
					</div>
					<div class="royal-divider"></div>
					<div>
						<p class="royal-discount-value">15% OFF</p>
						<p class="royal-discount-label">Extended Sojourns</p>
					</div>
				</div>
				<p class="royal-note">By order of the Royal Treasury, guests tendering their dues in physical coin or note shall receive the aforementioned grace upon their total tribute.</p>
			</div>
		</div>
	</section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
