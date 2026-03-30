<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function submitReservation(array $post, PDO $pdo): array
{
	$roomTypeOptions = ['Suite', 'De Luxe', 'Regular'];
	$capacityOptions = ['Family', 'Double', 'Single'];
	$paymentOptions = ['Cash', 'Cheque', 'Credit Card'];
	$checkInRaw = trim((string)($post['check_in'] ?? ''));
	$checkOutRaw = trim((string)($post['check_out'] ?? ''));
	$errors = [];

	$data = [
		'customer_name' => trim((string)($post['customer_name'] ?? '')),
		'contact_number' => trim((string)($post['contact_number'] ?? '')),
		'from_month' => trim((string)($post['from_month'] ?? '')),
		'from_day' => trim((string)($post['from_day'] ?? '')),
		'from_year' => trim((string)($post['from_year'] ?? '')),
		'to_month' => trim((string)($post['to_month'] ?? '')),
		'to_day' => trim((string)($post['to_day'] ?? '')),
		'to_year' => trim((string)($post['to_year'] ?? '')),
		'room_type' => trim((string)($post['room_type'] ?? '')),
		'room_capacity' => trim((string)($post['room_capacity'] ?? '')),
		'payment_type' => trim((string)($post['payment_type'] ?? '')),
	];

	$checkInDate = null;
	$checkOutDate = null;

	if ($checkInRaw !== '') {
		$checkInDate = DateTime::createFromFormat('Y-m-d', $checkInRaw);
		if ($checkInDate instanceof DateTime && $checkInDate->format('Y-m-d') === $checkInRaw) {
			$data['from_month'] = $checkInDate->format('n');
			$data['from_day'] = $checkInDate->format('j');
			$data['from_year'] = $checkInDate->format('Y');
		} else {
			$errors[] = 'Please choose a valid check-in date.';
		}
	}

	if ($checkOutRaw !== '') {
		$checkOutDate = DateTime::createFromFormat('Y-m-d', $checkOutRaw);
		if ($checkOutDate instanceof DateTime && $checkOutDate->format('Y-m-d') === $checkOutRaw) {
			$data['to_month'] = $checkOutDate->format('n');
			$data['to_day'] = $checkOutDate->format('j');
			$data['to_year'] = $checkOutDate->format('Y');
		} else {
			$errors[] = 'Please choose a valid check-out date.';
		}
	}

	foreach ($data as $key => $value) {
		if ($value === '') {
			$errors[] = 'Please fill in all fields before submitting the reservation.';
			break;
		}
	}

	if (mb_strlen($data['customer_name']) < 3) {
		$errors[] = 'Guest name must be at least 3 characters.';
	}

	if (!preg_match('/^[0-9+()\-\s]{7,20}$/', $data['contact_number'])) {
		$errors[] = 'Please provide a valid contact number.';
	}

	if (!in_array($data['room_type'], $roomTypeOptions, true)) {
		$errors[] = 'Please select a valid room type.';
	}
	if (!in_array($data['room_capacity'], $capacityOptions, true)) {
		$errors[] = 'Please select a valid room capacity.';
	}
	if (!in_array($data['payment_type'], $paymentOptions, true)) {
		$errors[] = 'Please select a valid payment type.';
	}

	$fromMonth = (int)$data['from_month'];
	$fromDay = (int)$data['from_day'];
	$fromYear = (int)$data['from_year'];
	$toMonth = (int)$data['to_month'];
	$toDay = (int)$data['to_day'];
	$toYear = (int)$data['to_year'];

	if (!checkdate($fromMonth, $fromDay, $fromYear) || !checkdate($toMonth, $toDay, $toYear)) {
		$errors[] = 'Please choose valid reservation dates.';
	}

	$fromDate = null;
	$toDate = null;
	if (empty($errors)) {
		$fromDate = new DateTime(sprintf('%04d-%02d-%02d', $fromYear, $fromMonth, $fromDay));
		$toDate = new DateTime(sprintf('%04d-%02d-%02d', $toYear, $toMonth, $toDay));
		$today = new DateTime('today');

		if ($fromDate < $today) {
			$errors[] = 'Check-in date cannot be in the past.';
		}

		if ($toDate <= $fromDate) {
			$errors[] = 'Check-out date must be at least 1 day after check-in.';
		}
	}

	if (!empty($errors)) {
		return [
			'success' => false,
			'errors' => array_values(array_unique($errors)),
			'data' => $data,
		];
	}

	$days = (int)$fromDate->diff($toDate)->format('%a');

	$ratePerDay = fetchRatePerDay($pdo, $data['room_capacity'], $data['room_type']);
	if ($ratePerDay === null) {
		return [
			'success' => false,
			'errors' => ['Unable to find rate for the selected room capacity and room type.'],
			'data' => $data,
		];
	}

	$subtotal = $ratePerDay * $days;
	$adjustLabel = 'Discount';
	$adjustValue = 0.00;

	if ($data['payment_type'] === 'Cash') {
		if ($days >= 6) {
			$adjustValue = $subtotal * 0.15;
		} elseif ($days >= 3) {
			$adjustValue = $subtotal * 0.10;
		}
		$total = $subtotal - $adjustValue;
	} elseif ($data['payment_type'] === 'Cheque') {
		$adjustLabel = 'Additional Charge';
		$adjustValue = $subtotal * 0.05;
		$total = $subtotal + $adjustValue;
	} else {
		$adjustLabel = 'Additional Charge';
		$adjustValue = $subtotal * 0.10;
		$total = $subtotal + $adjustValue;
	}

	$reservedAt = new DateTime();

	saveReservation($pdo, [
		'customer_name' => $data['customer_name'],
		'contact_number' => $data['contact_number'],
		'from_date' => $fromDate->format('Y-m-d'),
		'to_date' => $toDate->format('Y-m-d'),
		'room_type' => $data['room_type'],
		'room_capacity' => $data['room_capacity'],
		'payment_type' => $data['payment_type'],
		'no_of_days' => $days,
		'rate_per_day' => $ratePerDay,
		'subtotal' => $subtotal,
		'adjust_label' => $adjustLabel,
		'adjust_value' => $adjustValue,
		'total_bill' => $total,
		'reserved_at' => $reservedAt->format('Y-m-d H:i:s'),
	]);

	return [
		'success' => true,
		'errors' => [],
		'data' => $data,
		'days' => $days,
		'rate_per_day' => $ratePerDay,
		'subtotal' => $subtotal,
		'adjust_label' => $adjustLabel,
		'adjust_value' => $adjustValue,
		'total' => $total,
		'from_date' => $fromDate,
		'to_date' => $toDate,
		'reserved_at' => $reservedAt,
	];
}

function e(string $value): string
{
	return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function dateValueFromParts(string $year, string $month, string $day): string
{
	if ($year === '' || $month === '' || $day === '') {
		return '';
	}

	$yearInt = (int)$year;
	$monthInt = (int)$month;
	$dayInt = (int)$day;
	if (!checkdate($monthInt, $dayInt, $yearInt)) {
		return '';
	}

	return sprintf('%04d-%02d-%02d', $yearInt, $monthInt, $dayInt);
}

function getRoomRateMatrix(): array
{
	return [
		'Single' => [
			'Regular' => 100.00,
			'De Luxe' => 300.00,
			'Suite' => 500.00,
		],
		'Double' => [
			'Regular' => 200.00,
			'De Luxe' => 500.00,
			'Suite' => 800.00,
		],
		'Family' => [
			'Regular' => 500.00,
			'De Luxe' => 750.00,
			'Suite' => 1000.00,
		],
	];
}

$activePage = 'reservation';
$pageTitle = "Reservation | Jero &amp; Vonn's Royal Chambers";
$htmlClass = 'dark';
$bodyClass = 'reservation-page selection:bg-primary-container selection:text-surface';
$headExtras = <<<'HTML'
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@400;700;900&amp;family=Crimson+Pro:ital,wght@0,200..900;1,200..900&amp;family=Cinzel:wght@400;700&amp;family=Public+Sans:wght@300;400;600&amp;display=swap" rel="stylesheet">
<script id="tailwind-config">
	tailwind.config = {
		darkMode: 'class',
		theme: {
			extend: {
				colors: {
					primary: '#f8bc51',
					'primary-container': '#c8922a',
					surface: '#131316',
					surface2: '#1e1e24',
					textgold: '#c8922a',
					textsoft: '#9a8f7e',
					textlight: '#e8e0d0'
				},
				fontFamily: {
					cinzeldec: ['Cinzel Decorative', 'serif'],
					cinzel: ['Cinzel', 'serif'],
					crimson: ['Crimson Pro', 'serif']
				}
			}
		}
	};
</script>
HTML;
$monthNames = [
	1 => 'January',
	2 => 'February',
	3 => 'March',
	4 => 'April',
	5 => 'May',
	6 => 'June',
	7 => 'July',
	8 => 'August',
	9 => 'September',
	10 => 'October',
	11 => 'November',
	12 => 'December',
];

$formData = [
	'customer_name' => '',
	'contact_number' => '',
	'from_month' => '',
	'from_day' => '',
	'from_year' => '',
	'to_month' => '',
	'to_day' => '',
	'to_year' => '',
	'room_type' => '',
	'room_capacity' => '',
	'payment_type' => '',
];

$errors = [];
$billing = null;
$showBilling = false;
$isSessionMode = isSessionStorageMode();
$storageNotice = $isSessionMode ? getStorageModeNotice() : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {
	try {
		$pdo = getPDO();
		initializeDatabase($pdo);
		$result = submitReservation($_POST, $pdo);
	} catch (Throwable $e) {
		error_log('[hotel-reservation] ' . $e->getMessage());

		$failureMessage = isSessionStorageMode()
			? 'Temporary mode is active and MySQL is not required. A runtime error occurred; check server logs for details.'
			: 'Database connection failed. Please check MySQL server and database settings in db.php.';

		$result = [
			'success' => false,
			'errors' => [$failureMessage],
			'data' => [
				'customer_name' => trim((string)($_POST['customer_name'] ?? '')),
				'contact_number' => trim((string)($_POST['contact_number'] ?? '')),
				'from_month' => trim((string)($_POST['from_month'] ?? '')),
				'from_day' => trim((string)($_POST['from_day'] ?? '')),
				'from_year' => trim((string)($_POST['from_year'] ?? '')),
				'to_month' => trim((string)($_POST['to_month'] ?? '')),
				'to_day' => trim((string)($_POST['to_day'] ?? '')),
				'to_year' => trim((string)($_POST['to_year'] ?? '')),
				'room_type' => trim((string)($_POST['room_type'] ?? '')),
				'room_capacity' => trim((string)($_POST['room_capacity'] ?? '')),
				'payment_type' => trim((string)($_POST['payment_type'] ?? '')),
			],
		];
	}
	$formData = $result['data'];
	$errors = $result['errors'];
	if ($result['success']) {
		$billing = $result;
		$showBilling = true;
	}
}

$todayIso = (new DateTime('today'))->format('Y-m-d');
$checkInValue = dateValueFromParts($formData['from_year'], $formData['from_month'], $formData['from_day']);
$checkOutValue = dateValueFromParts($formData['to_year'], $formData['to_month'], $formData['to_day']);
$roomRateMatrixJson = json_encode(getRoomRateMatrix(), JSON_UNESCAPED_SLASHES);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main>
	<section class="relative h-[307px] flex flex-col items-center justify-center text-center px-6 overflow-hidden">
		<div class="absolute inset-0 z-0">
			<div class="w-full h-full bg-cover bg-center opacity-30 grayscale" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBaaEWF4eyxT3Xv2FTIGTgJwDLI8de4PDxSqu3ihp7nnasw1zzdEZapzmkSpLKGnU2uP-wDI-Hx4x8lDO0qyBLkwQ60BlC6BeMIIgJpgWq6mDgNF3tnKCTX3lGMcm4uPx0G9sNJ6NnuhAA_vCmSm_E8XK5ExTFgc0cvlsd_I_X1pBxNHpXDFG2R5flyjQGNWvQXYOnvwb38_RFg8-k-EfXaYW9SqVY8RJ9SmKok3Ebw-fbjwDT369ga7Xq_UxnnxIilMv0bT15XFRI');"></div>
			<div class="absolute inset-0 royal-hero-gradient"></div>
		</div>
		<div class="relative z-10" style="animation: riseIn 0.8s ease;">
			<h1 class="font-cinzeldec text-4xl md:text-5xl lg:text-6xl text-textgold tracking-[0.2em] mb-4">RESERVE A CHAMBER</h1>
			<p class="font-crimson italic text-lg md:text-xl text-textsoft max-w-2xl mx-auto">Complete the scroll below to secure your quarters.</p>
		</div>
	</section>

	<section class="max-w-5xl mx-auto px-6 py-12">
		<?php if ($storageNotice !== '') : ?>
			<div class="success-box mb-8" id="storage-mode-box">
				<div><?php echo e($storageNotice); ?></div>
			</div>
		<?php endif; ?>

		<div class="mb-12 p-6 bg-[#151518] border border-[#c8922a]/40 rounded-sm relative overflow-hidden">
			<div class="absolute top-0 left-0 w-1 h-full bg-[#c8922a]"></div>
			<div class="pl-4">
				<h3 class="font-cinzel text-textgold text-sm tracking-widest mb-2">BY ROYAL DECREE</h3>
				<p class="font-crimson text-textlight leading-relaxed">
					Cash payments receive <span class="text-textgold font-bold">10% favour</span> for 3-5 nights, and <span class="text-textgold font-bold">15% favour</span> for 6 nights or more. Cheque adds 5%. Credit Card adds 10%.
				</p>
			</div>
		</div>

		<section id="reservation-form-section" class="bg-surface2 border border-[#c8922a]/20 shadow-2xl p-8 md:p-12" <?php echo $showBilling ? 'style="display:none;"' : ''; ?>>
			<?php if (!empty($errors)) : ?>
				<div class="error-box mb-8" id="error-box">
					<?php foreach ($errors as $error) : ?>
						<div><?php echo e($error); ?></div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<form id="reservation-form" method="post" action="reservation.php" class="space-y-12">
				<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
					<div class="space-y-2">
						<label class="font-cinzel text-xs tracking-widest text-textsoft" for="customer_name">GUEST NAME</label>
						<input class="royal-input w-full px-4 py-3 font-crimson text-lg rounded-sm transition-all" id="customer_name" name="customer_name" placeholder="Enter full title and name" type="text" maxlength="90" autocomplete="name" required value="<?php echo e($formData['customer_name']); ?>">
					</div>
					<div class="space-y-2">
						<label class="font-cinzel text-xs tracking-widest text-textsoft" for="contact_number">CONTACT NUMBER</label>
						<input class="royal-input w-full px-4 py-3 font-crimson text-lg rounded-sm transition-all" id="contact_number" name="contact_number" placeholder="+63 912 345 6789" type="text" maxlength="20" inputmode="tel" autocomplete="tel" pattern="[0-9+()\-\s]{7,20}" required value="<?php echo e($formData['contact_number']); ?>">
					</div>
				</div>

				<input type="hidden" name="from_month" id="from_month" value="<?php echo e($formData['from_month']); ?>">
				<input type="hidden" name="from_day" id="from_day" value="<?php echo e($formData['from_day']); ?>">
				<input type="hidden" name="from_year" id="from_year" value="<?php echo e($formData['from_year']); ?>">
				<input type="hidden" name="to_month" id="to_month" value="<?php echo e($formData['to_month']); ?>">
				<input type="hidden" name="to_day" id="to_day" value="<?php echo e($formData['to_day']); ?>">
				<input type="hidden" name="to_year" id="to_year" value="<?php echo e($formData['to_year']); ?>">

				<div class="space-y-6">
					<div class="flex items-center gap-4">
						<div class="h-px flex-1 bg-[#c8922a]/20"></div>
						<h2 class="font-cinzel text-textgold tracking-[0.3em] text-sm px-4">DATES OF STAY</h2>
						<div class="h-px flex-1 bg-[#c8922a]/20"></div>
					</div>
					<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
						<div class="royal-date-card">
							<label class="royal-date-label" for="check_in">CHECK-IN</label>
							<input class="royal-input w-full px-4 py-3 text-lg" type="date" id="check_in" name="check_in" min="<?php echo e($todayIso); ?>" required value="<?php echo e($checkInValue); ?>">
							<p class="royal-date-helper">Arrive any time after 2:00 PM.</p>
						</div>
						<div class="royal-date-card">
							<label class="royal-date-label" for="check_out">CHECK-OUT</label>
							<input class="royal-input w-full px-4 py-3 text-lg" type="date" id="check_out" name="check_out" min="<?php echo e($todayIso); ?>" required value="<?php echo e($checkOutValue); ?>">
							<p class="royal-date-helper">Departure before 12:00 PM.</p>
						</div>
					</div>
					<div class="royal-stay-chip" id="stay-nights">Select dates to view total nights.</div>
				</div>

				<div class="grid grid-cols-1 lg:grid-cols-3 gap-12 pt-4">
					<div class="space-y-6">
						<h3 class="font-cinzel text-xs tracking-widest text-textgold border-b border-[#c8922a]/20 pb-2">CHAMBER TYPE</h3>
						<div class="space-y-4">
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="room_type" type="radio" value="Suite" <?php echo $formData['room_type'] === 'Suite' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">Suite</span>
							</label>
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="room_type" type="radio" value="De Luxe" <?php echo $formData['room_type'] === 'De Luxe' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">De Luxe</span>
							</label>
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="room_type" type="radio" value="Regular" <?php echo $formData['room_type'] === 'Regular' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">Regular</span>
							</label>
						</div>
					</div>

					<div class="space-y-6">
						<h3 class="font-cinzel text-xs tracking-widest text-textgold border-b border-[#c8922a]/20 pb-2">CAPACITY</h3>
						<div class="space-y-4">
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="room_capacity" type="radio" value="Family" <?php echo $formData['room_capacity'] === 'Family' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">Family</span>
							</label>
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="room_capacity" type="radio" value="Double" <?php echo $formData['room_capacity'] === 'Double' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">Double</span>
							</label>
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="room_capacity" type="radio" value="Single" <?php echo $formData['room_capacity'] === 'Single' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">Single</span>
							</label>
						</div>
					</div>

					<div class="space-y-6">
						<h3 class="font-cinzel text-xs tracking-widest text-textgold border-b border-[#c8922a]/20 pb-2">PAYMENT METHOD</h3>
						<div class="space-y-4">
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="payment_type" type="radio" value="Cash" <?php echo $formData['payment_type'] === 'Cash' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">Cash</span>
							</label>
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="payment_type" type="radio" value="Cheque" <?php echo $formData['payment_type'] === 'Cheque' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">Cheque</span>
							</label>
							<label class="flex items-center gap-3 cursor-pointer group">
								<input class="w-4 h-4 text-[#c8922a] bg-[#131316] border-[#c8922a]/40 focus:ring-offset-[#1e1e24] focus:ring-[#c8922a]" name="payment_type" type="radio" value="Credit Card" <?php echo $formData['payment_type'] === 'Credit Card' ? 'checked' : ''; ?>>
								<span class="font-crimson text-textlight group-hover:text-textgold transition-colors">Credit Card</span>
							</label>
						</div>
					</div>
				</div>

				<div class="royal-preview-card" id="price-preview-card">
					<div class="royal-preview-header">
						<h3 class="royal-preview-title">ESTIMATED BILL PREVIEW</h3>
						<p class="royal-preview-note">Final billed amount is confirmed after form submission.</p>
					</div>
					<div class="royal-preview-grid">
						<div class="royal-preview-row">
							<span>Rate / Night</span>
							<strong id="preview-rate">-</strong>
						</div>
						<div class="royal-preview-row">
							<span>No. of Nights</span>
							<strong id="preview-nights">-</strong>
						</div>
						<div class="royal-preview-row">
							<span>Sub-Total</span>
							<strong id="preview-subtotal">-</strong>
						</div>
						<div class="royal-preview-row">
							<span id="preview-adjust-label">Adjustment</span>
							<strong id="preview-adjust-value">-</strong>
						</div>
					</div>
					<div class="royal-preview-total-row">
						<span>Estimated Total</span>
						<strong id="preview-total">-</strong>
					</div>
					<p class="royal-preview-helper" id="preview-helper">Select stay dates, room type, capacity, and payment method to generate estimate.</p>
				</div>

				<div class="flex flex-col md:flex-row gap-6 pt-12">
					<button class="flex-1 bg-[#c8922a] text-[#0d0d0f] font-cinzel font-bold py-4 tracking-widest hover:bg-[#e0a83a] transition-all transform active:scale-95" name="submit_reservation" type="submit">
						CONFIRM RESERVATION
					</button>
					<button class="flex-1 border border-[#c8922a] text-[#c8922a] font-cinzel font-bold py-4 tracking-widest hover:bg-[#c8922a]/10 transition-all transform active:scale-95" type="button" onclick="clearEntry()">
						CLEAR THE SCROLL
					</button>
				</div>
			</form>
		</section>

		<section id="billing-section" class="bg-surface2 border border-[#c8922a]/20 shadow-2xl p-8 md:p-12" <?php echo $showBilling ? '' : 'style="display:none;"'; ?>>
			<?php if ($billing !== null) : ?>
				<h2 class="font-cinzel text-textgold tracking-[0.2em] text-2xl mb-8 text-center">RESERVATION BILLING INFORMATION</h2>
				<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
					<div class="bg-[#151518] border border-[#c8922a]/30 p-5">
						<p><span class="text-textsoft">Customer Name:</span> <strong class="text-textlight"><?php echo e($billing['data']['customer_name']); ?></strong></p>
						<p><span class="text-textsoft">Contact Number:</span> <strong class="text-textlight"><?php echo e($billing['data']['contact_number']); ?></strong></p>
						<p><span class="text-textsoft">Date Reserved:</span> <strong class="text-textlight"><?php echo $billing['reserved_at']->format('F j, Y'); ?></strong></p>
						<p><span class="text-textsoft">Time:</span> <strong class="text-textlight"><?php echo $billing['reserved_at']->format('h:i:s A'); ?></strong></p>
					</div>
					<div class="bg-[#151518] border border-[#c8922a]/30 p-5">
						<p><span class="text-textsoft">Reservation From:</span> <strong class="text-textlight"><?php echo $billing['from_date']->format('F j, Y'); ?></strong></p>
						<p><span class="text-textsoft">Reservation To:</span> <strong class="text-textlight"><?php echo $billing['to_date']->format('F j, Y'); ?></strong></p>
						<p><span class="text-textsoft">Room Type:</span> <strong class="text-textlight"><?php echo e($billing['data']['room_type']); ?></strong></p>
						<p><span class="text-textsoft">Room Capacity:</span> <strong class="text-textlight"><?php echo e($billing['data']['room_capacity']); ?></strong></p>
						<p><span class="text-textsoft">Payment Type:</span> <strong class="text-textlight"><?php echo e($billing['data']['payment_type']); ?></strong></p>
					</div>
				</div>

				<div class="overflow-x-auto">
					<table class="w-full border border-[#c8922a]/25 text-left">
						<thead class="bg-[#151518]">
							<tr>
								<th class="px-4 py-3 font-cinzel tracking-widest text-textgold text-sm" colspan="2">BILLING STATEMENTS</th>
							</tr>
						</thead>
						<tbody>
							<tr class="border-t border-[#c8922a]/20">
								<td class="px-4 py-3">No. of Days</td>
								<td class="px-4 py-3"><?php echo $billing['days']; ?></td>
							</tr>
							<tr class="border-t border-[#c8922a]/20">
								<td class="px-4 py-3">Sub-Total</td>
								<td class="px-4 py-3"><?php echo number_format($billing['subtotal'], 2); ?></td>
							</tr>
							<tr class="border-t border-[#c8922a]/20">
								<td class="px-4 py-3"><?php echo e($billing['adjust_label']); ?></td>
								<td class="px-4 py-3"><?php echo number_format($billing['adjust_value'], 2); ?></td>
							</tr>
						</tbody>
						<tfoot>
							<tr class="border-t border-[#c8922a]/30 bg-[#151518]">
								<th class="px-4 py-3 font-cinzel tracking-wider">Total Bill</th>
								<th class="px-4 py-3 font-cinzel tracking-wider"><?php echo number_format($billing['total'], 2); ?></th>
							</tr>
						</tfoot>
					</table>
				</div>

				<div class="flex flex-col md:flex-row gap-6 pt-10">
					<a class="flex-1 text-center bg-[#c8922a] text-[#0d0d0f] font-cinzel font-bold py-4 tracking-widest hover:bg-[#e0a83a] transition-all" href="home.php">HOME</a>
					<a class="flex-1 text-center border border-[#c8922a] text-[#c8922a] font-cinzel font-bold py-4 tracking-widest hover:bg-[#c8922a]/10 transition-all" href="reservation.php">BACK</a>
				</div>
			<?php endif; ?>
		</section>
	</section>
</main>

<?php
$footerScripts = <<<'HTML'
<script>
const ROOM_RATES = __ROOM_RATES__;

function toCurrency(value) {
	if (typeof value !== 'number' || !isFinite(value)) {
		return '-';
	}

	return value.toLocaleString('en-PH', {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2
	});
}

function getSelectedValue(name) {
	var checked = document.querySelector('input[name="' + name + '"]:checked');
	return checked ? checked.value : '';
}

function getNightsCount() {
	var checkInInput = document.getElementById('check_in');
	var checkOutInput = document.getElementById('check_out');
	if (!checkInInput || !checkOutInput || !checkInInput.value || !checkOutInput.value) {
		return 0;
	}

	var checkInDate = new Date(checkInInput.value + 'T00:00:00');
	var checkOutDate = new Date(checkOutInput.value + 'T00:00:00');
	var diffMs = checkOutDate.getTime() - checkInDate.getTime();
	var nights = Math.round(diffMs / 86400000);
	return nights > 0 ? nights : 0;
}

function updatePricePreview() {
	var rateField = document.getElementById('preview-rate');
	var nightsField = document.getElementById('preview-nights');
	var subtotalField = document.getElementById('preview-subtotal');
	var adjustLabelField = document.getElementById('preview-adjust-label');
	var adjustValueField = document.getElementById('preview-adjust-value');
	var totalField = document.getElementById('preview-total');
	var helperField = document.getElementById('preview-helper');
	if (!rateField || !nightsField || !subtotalField || !adjustLabelField || !adjustValueField || !totalField || !helperField) {
		return;
	}

	var roomType = getSelectedValue('room_type');
	var roomCapacity = getSelectedValue('room_capacity');
	var paymentType = getSelectedValue('payment_type');
	var nights = getNightsCount();

	if (!roomType || !roomCapacity || !paymentType || nights < 1) {
		rateField.textContent = '-';
		nightsField.textContent = nights > 0 ? String(nights) : '-';
		subtotalField.textContent = '-';
		adjustLabelField.textContent = 'Adjustment';
		adjustValueField.textContent = '-';
		totalField.textContent = '-';
		helperField.textContent = 'Select stay dates, room type, capacity, and payment method to generate estimate.';
		return;
	}

	var ratePerDay = ROOM_RATES[roomCapacity] && ROOM_RATES[roomCapacity][roomType] ? Number(ROOM_RATES[roomCapacity][roomType]) : 0;
	if (ratePerDay <= 0) {
		helperField.textContent = 'Selected room combination has no rate configured yet.';
		rateField.textContent = '-';
		nightsField.textContent = String(nights);
		subtotalField.textContent = '-';
		adjustLabelField.textContent = 'Adjustment';
		adjustValueField.textContent = '-';
		totalField.textContent = '-';
		return;
	}

	var subtotal = ratePerDay * nights;
	var adjustLabel = 'Discount';
	var adjustValue = 0;
	var total = subtotal;

	if (paymentType === 'Cash') {
		if (nights >= 6) {
			adjustValue = subtotal * 0.15;
		} else if (nights >= 3) {
			adjustValue = subtotal * 0.10;
		}
		total = subtotal - adjustValue;
	} else if (paymentType === 'Cheque') {
		adjustLabel = 'Additional Charge';
		adjustValue = subtotal * 0.05;
		total = subtotal + adjustValue;
	} else if (paymentType === 'Credit Card') {
		adjustLabel = 'Additional Charge';
		adjustValue = subtotal * 0.10;
		total = subtotal + adjustValue;
	}

	rateField.textContent = toCurrency(ratePerDay);
	nightsField.textContent = String(nights);
	subtotalField.textContent = toCurrency(subtotal);
	adjustLabelField.textContent = adjustLabel;
	adjustValueField.textContent = toCurrency(adjustValue);
	totalField.textContent = toCurrency(total);
	helperField.textContent = paymentType === 'Cash' ? 'Cash discount is automatically estimated based on selected number of nights.' : 'Surcharge estimate applied based on selected payment method.';
}

function clearEntry() {
	var form = document.getElementById('reservation-form');
	if (!form) {
		return;
	}

	var textInputs = form.querySelectorAll('input[type="text"], input[type="date"]');
	textInputs.forEach(function(input) {
		input.value = '';
	});

	var radios = form.querySelectorAll('input[type="radio"]');
	radios.forEach(function(radio) {
		radio.checked = false;
	});

	var hiddenDateParts = ['from_month', 'from_day', 'from_year', 'to_month', 'to_day', 'to_year'];
	hiddenDateParts.forEach(function(fieldId) {
		var hiddenInput = document.getElementById(fieldId);
		if (hiddenInput) {
			hiddenInput.value = '';
		}
	});

	var errorBox = document.getElementById('error-box');
	if (errorBox) {
		errorBox.style.display = 'none';
	}

	var formSection = document.getElementById('reservation-form-section');
	var billingSection = document.getElementById('billing-section');
	if (billingSection && formSection) {
		billingSection.style.display = 'none';
		formSection.style.display = 'block';
	}

	var stayNights = document.getElementById('stay-nights');
	if (stayNights) {
		stayNights.textContent = 'Select dates to view total nights.';
	}

	var checkInInput = document.getElementById('check_in');
	var checkOutInput = document.getElementById('check_out');
	if (checkOutInput && checkInInput) {
		checkOutInput.min = checkInInput.min;
	}

	updatePricePreview();
}

function syncDateParts(dateValue, prefix) {
	var monthField = document.getElementById(prefix + '_month');
	var dayField = document.getElementById(prefix + '_day');
	var yearField = document.getElementById(prefix + '_year');

	if (!monthField || !dayField || !yearField) {
		return;
	}

	if (!dateValue) {
		monthField.value = '';
		dayField.value = '';
		yearField.value = '';
		return;
	}

	var parts = dateValue.split('-');
	if (parts.length !== 3) {
		return;
	}

	yearField.value = String(parseInt(parts[0], 10));
	monthField.value = String(parseInt(parts[1], 10));
	dayField.value = String(parseInt(parts[2], 10));
}

function showClientErrors(messages) {
	var errorBox = document.getElementById('error-box');
	if (!errorBox) {
		errorBox = document.createElement('div');
		errorBox.id = 'error-box';
		errorBox.className = 'error-box mb-8';
		var formSection = document.getElementById('reservation-form-section');
		if (formSection) {
			formSection.insertBefore(errorBox, formSection.firstChild);
		}
	}

	errorBox.innerHTML = '';
	messages.forEach(function(message) {
		var row = document.createElement('div');
		row.textContent = message;
		errorBox.appendChild(row);
	});
	errorBox.style.display = 'block';
}

function updateStayNights() {
	var checkInInput = document.getElementById('check_in');
	var checkOutInput = document.getElementById('check_out');
	var stayNights = document.getElementById('stay-nights');
	if (!checkInInput || !checkOutInput || !stayNights) {
		return;
	}

	if (checkInInput.value) {
		var checkInParts = checkInInput.value.split('-');
		if (checkInParts.length === 3) {
			var minCheckoutDate = new Date(
				parseInt(checkInParts[0], 10),
				parseInt(checkInParts[1], 10) - 1,
				parseInt(checkInParts[2], 10) + 1
			);
			var minYear = minCheckoutDate.getFullYear();
			var minMonth = String(minCheckoutDate.getMonth() + 1).padStart(2, '0');
			var minDay = String(minCheckoutDate.getDate()).padStart(2, '0');
			checkOutInput.min = minYear + '-' + minMonth + '-' + minDay;
		}
	}

	if (!checkInInput.value || !checkOutInput.value) {
		stayNights.textContent = 'Select dates to view total nights.';
		return;
	}

	var checkInDate = new Date(checkInInput.value + 'T00:00:00');
	var checkOutDate = new Date(checkOutInput.value + 'T00:00:00');
	var diffMs = checkOutDate.getTime() - checkInDate.getTime();
	var nights = Math.round(diffMs / 86400000);
	if (nights > 0) {
		stayNights.textContent = nights + (nights === 1 ? ' night stay' : ' nights stay');
	} else {
		stayNights.textContent = 'Check-out must be after check-in.';
	}

	updatePricePreview();
}

document.addEventListener('DOMContentLoaded', function() {
	var form = document.getElementById('reservation-form');
	var checkInInput = document.getElementById('check_in');
	var checkOutInput = document.getElementById('check_out');
	var customerNameInput = document.getElementById('customer_name');
	var contactNumberInput = document.getElementById('contact_number');

	if (!form || !checkInInput || !checkOutInput) {
		return;
	}

	updateStayNights();

	var pricingInputs = form.querySelectorAll('input[name="room_type"], input[name="room_capacity"], input[name="payment_type"]');
	pricingInputs.forEach(function(input) {
		input.addEventListener('change', function() {
			updatePricePreview();
		});
	});

	checkInInput.addEventListener('change', function() {
		syncDateParts(checkInInput.value, 'from');
		if (checkOutInput.value && checkOutInput.value <= checkInInput.value) {
			checkOutInput.value = '';
			syncDateParts('', 'to');
		}
		updateStayNights();
	});

	checkOutInput.addEventListener('change', function() {
		syncDateParts(checkOutInput.value, 'to');
		updateStayNights();
	});

	updatePricePreview();

	form.addEventListener('submit', function(event) {
		var messages = [];
		var contactPattern = /^[0-9+()\-\s]{7,20}$/;

		syncDateParts(checkInInput.value, 'from');
		syncDateParts(checkOutInput.value, 'to');

		if (!customerNameInput.value || customerNameInput.value.trim().length < 3) {
			messages.push('Guest name must be at least 3 characters.');
		}

		if (!contactPattern.test(contactNumberInput.value.trim())) {
			messages.push('Please provide a valid contact number.');
		}

		if (!checkInInput.value || !checkOutInput.value) {
			messages.push('Please select check-in and check-out dates.');
		} else if (checkOutInput.value <= checkInInput.value) {
			messages.push('Check-out date must be at least 1 day after check-in.');
		}

		if (messages.length > 0) {
			event.preventDefault();
			showClientErrors(messages);
		}
	});
});
</script>
HTML;
$footerScripts = str_replace('__ROOM_RATES__', $roomRateMatrixJson !== false ? $roomRateMatrixJson : '{}', $footerScripts);
?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
