<?php
$activePage = 'contacts';
$pageTitle = "Contacts | Jero &amp; Vonn's Six Star Hotel";
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main>
	<section class="contacts-hero" aria-label="Contact Hero">
		<div class="contacts-hero-content">
			<h1>Reach Our Chambers</h1>
			<p>For reservations, events, and special requests, our concierge team is ready to answer day or night.</p>
		</div>
		<div class="contacts-hero-fade" aria-hidden="true"></div>
	</section>

	<section class="contacts-main">
		<div class="royal-container contacts-grid">
			<div class="contacts-left">
				<h2 class="contacts-heading">Find Our Chambers</h2>
				<div class="contacts-info-list">
					<div class="contacts-info-item">
						<span class="material-symbols-outlined" aria-hidden="true">location_on</span>
						<div>
							<p class="contacts-label">Our Address</p>
							<p class="contacts-value">123 Hotel Street, Manila, Philippines</p>
						</div>
					</div>
					<div class="contacts-info-item">
						<span class="material-symbols-outlined" aria-hidden="true">schedule</span>
						<div>
							<p class="contacts-label">Front Desk Hours</p>
							<p class="contacts-value">Open 24 Hours, Every Day</p>
						</div>
					</div>
					<div class="contacts-info-item">
						<span class="material-symbols-outlined" aria-hidden="true">call</span>
						<div>
							<p class="contacts-label">Phone Number</p>
							<p class="contacts-value">+63 2 8123 4567</p>
						</div>
					</div>
					<div class="contacts-info-item">
						<span class="material-symbols-outlined" aria-hidden="true">mail</span>
						<div>
							<p class="contacts-label">Email Address</p>
							<p class="contacts-value">reservations@jerovonnhotel.com</p>
						</div>
					</div>
				</div>

				<div class="contacts-map-card">
					<p class="contacts-map-title">Map of Our Grounds</p>
					<div class="contacts-map-wrap">
						<iframe
							src="https://www.google.com/maps?q=Manila%2C%20Philippines&amp;output=embed"
							loading="lazy"
							referrerpolicy="no-referrer-when-downgrade"
							title="Hotel Location Map">
						</iframe>
					</div>
				</div>
			</div>

			<div class="contacts-right">
				<section class="contacts-form-card" aria-labelledby="send-message-title">
					<h2 id="send-message-title" class="contacts-heading contacts-heading-center">Send a Message</h2>
					<form action="#" method="post" class="contacts-form" novalidate>
						<div class="contacts-field-row">
							<label for="name">Your Name</label>
							<input type="text" id="name" name="name" placeholder="Enter your name">
						</div>
						<div class="contacts-field-row">
							<label for="email">Email Address</label>
							<input type="email" id="email" name="email" placeholder="Where should we reply?">
						</div>
						<div class="contacts-field-row">
							<label for="message">Message</label>
							<textarea id="message" name="message" rows="5" placeholder="Write your request here..."></textarea>
						</div>
						<button type="submit" class="contacts-submit">Dispatch Message</button>
					</form>
				</section>
				<p class="contacts-quote">"A prompt response is the hallmark of true nobility."</p>
			</div>
		</div>
	</section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
