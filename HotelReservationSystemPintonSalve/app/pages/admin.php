<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function normalizeAdminListState(array $source, array $roomTypeOptions, array $paymentOptions, array $perPageOptions): array
{
    $state = [
        'search' => trim((string)($source['search'] ?? '')),
        'payment_type' => trim((string)($source['payment_type'] ?? '')),
        'room_type' => trim((string)($source['room_type'] ?? '')),
        'from_date' => trim((string)($source['from_date'] ?? '')),
        'to_date' => trim((string)($source['to_date'] ?? '')),
        'page' => max(1, (int)($source['page'] ?? 1)),
        'per_page' => (int)($source['per_page'] ?? $perPageOptions[0]),
    ];

    if (!in_array($state['payment_type'], $paymentOptions, true)) {
        $state['payment_type'] = '';
    }
    if (!in_array($state['room_type'], $roomTypeOptions, true)) {
        $state['room_type'] = '';
    }

    if (!in_array($state['per_page'], $perPageOptions, true)) {
        $state['per_page'] = $perPageOptions[0];
    }

    foreach (['from_date', 'to_date'] as $dateField) {
        if ($state[$dateField] === '') {
            continue;
        }

        $date = DateTime::createFromFormat('Y-m-d', $state[$dateField]);
        if ($date === false || $date->format('Y-m-d') !== $state[$dateField]) {
            $state[$dateField] = '';
        }
    }

    return $state;
}

function buildAdminListQuery(array $state, array $overrides = []): string
{
    $merged = array_merge($state, $overrides);
    $params = [];

    foreach (['search', 'payment_type', 'room_type', 'from_date', 'to_date'] as $textKey) {
        $value = trim((string)($merged[$textKey] ?? ''));
        if ($value !== '') {
            $params[$textKey] = $value;
        }
    }

    $params['page'] = max(1, (int)($merged['page'] ?? 1));
    $params['per_page'] = max(1, (int)($merged['per_page'] ?? 10));

    return http_build_query($params);
}

function calculateBilling(PDO $pdo, string $roomCapacity, string $roomType, string $paymentType, DateTime $fromDate, DateTime $toDate): array
{
    $days = (int)$fromDate->diff($toDate)->format('%r%a');
    if ($days < 1) {
        $days = 1;
    }

    $ratePerDay = fetchRatePerDay($pdo, $roomCapacity, $roomType);
    if ($ratePerDay === null) {
        throw new RuntimeException('Rate was not found for the selected room configuration.');
    }

    $subtotal = $ratePerDay * $days;
    $adjustLabel = 'Discount';
    $adjustValue = 0.00;

    if ($paymentType === 'Cash') {
        if ($days >= 6) {
            $adjustValue = $subtotal * 0.15;
        } elseif ($days >= 3) {
            $adjustValue = $subtotal * 0.10;
        }
        $totalBill = $subtotal - $adjustValue;
    } elseif ($paymentType === 'Cheque') {
        $adjustLabel = 'Additional Charge';
        $adjustValue = $subtotal * 0.05;
        $totalBill = $subtotal + $adjustValue;
    } else {
        $adjustLabel = 'Additional Charge';
        $adjustValue = $subtotal * 0.10;
        $totalBill = $subtotal + $adjustValue;
    }

    return [
        'no_of_days' => $days,
        'rate_per_day' => $ratePerDay,
        'subtotal' => $subtotal,
        'adjust_label' => $adjustLabel,
        'adjust_value' => $adjustValue,
        'total_bill' => $totalBill,
    ];
}

$activePage = 'admin';
$pageTitle = "Admin | Jero &amp; Vonn's Six Star Hotel";
$roomTypeOptions = ['Suite', 'De Luxe', 'Regular'];
$capacityOptions = ['Family', 'Double', 'Single'];
$paymentOptions = ['Cash', 'Cheque', 'Credit Card'];
$perPageOptions = [10, 20, 50];

$messages = [];
$infoNotices = [];
$errors = [];
$editReservation = null;
$reservations = [];
$totalReservations = 0;
$totalPages = 1;
$hasActiveFilters = false;
$isSessionMode = isSessionStorageMode();

if ($isSessionMode) {
    $infoNotices[] = getStorageModeNotice();
}

$listState = normalizeAdminListState($_GET, $roomTypeOptions, $paymentOptions, $perPageOptions);

try {
    $pdo = getPDO();
    initializeDatabase($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ((string)($_POST['list_context'] ?? '') === '1') {
            $listState = normalizeAdminListState($_POST, $roomTypeOptions, $paymentOptions, $perPageOptions);
        }

        $action = trim((string)($_POST['action'] ?? ''));
        $id = (int)($_POST['id'] ?? 0);

        if ($action === 'delete') {
            if ($id < 1) {
                $errors[] = 'Invalid reservation ID for delete action.';
            } else {
                $deleted = deleteReservationById($pdo, $id);
                if ($deleted) {
                    $messages[] = 'Reservation deleted successfully.';
                } else {
                    $errors[] = 'No reservation was deleted. It may no longer exist.';
                }
            }
        }

        if ($action === 'update') {
            $data = [
                'id' => $id,
                'customer_name' => trim((string)($_POST['customer_name'] ?? '')),
                'contact_number' => trim((string)($_POST['contact_number'] ?? '')),
                'from_date' => trim((string)($_POST['from_date'] ?? '')),
                'to_date' => trim((string)($_POST['to_date'] ?? '')),
                'room_type' => trim((string)($_POST['room_type'] ?? '')),
                'room_capacity' => trim((string)($_POST['room_capacity'] ?? '')),
                'payment_type' => trim((string)($_POST['payment_type'] ?? '')),
            ];

            foreach ($data as $key => $value) {
                if ($key === 'id') {
                    continue;
                }
                if ($value === '') {
                    $errors[] = 'Please fill in all fields before updating.';
                    break;
                }
            }

            if ($id < 1) {
                $errors[] = 'Invalid reservation ID for update action.';
            }
            if (!in_array($data['room_type'], $roomTypeOptions, true)) {
                $errors[] = 'Invalid room type selected.';
            }
            if (!in_array($data['room_capacity'], $capacityOptions, true)) {
                $errors[] = 'Invalid room capacity selected.';
            }
            if (!in_array($data['payment_type'], $paymentOptions, true)) {
                $errors[] = 'Invalid payment type selected.';
            }

            $fromDate = DateTime::createFromFormat('Y-m-d', $data['from_date']);
            $toDate = DateTime::createFromFormat('Y-m-d', $data['to_date']);
            if ($fromDate === false || $toDate === false) {
                $errors[] = 'Please provide valid from/to dates.';
            }

            if (empty($errors) && $fromDate !== false && $toDate !== false) {
                try {
                    $billing = calculateBilling(
                        $pdo,
                        $data['room_capacity'],
                        $data['room_type'],
                        $data['payment_type'],
                        $fromDate,
                        $toDate
                    );

                    $updated = updateReservationById($pdo, $id, [
                        'customer_name' => $data['customer_name'],
                        'contact_number' => $data['contact_number'],
                        'from_date' => $fromDate->format('Y-m-d'),
                        'to_date' => $toDate->format('Y-m-d'),
                        'room_type' => $data['room_type'],
                        'room_capacity' => $data['room_capacity'],
                        'payment_type' => $data['payment_type'],
                        'no_of_days' => $billing['no_of_days'],
                        'rate_per_day' => $billing['rate_per_day'],
                        'subtotal' => $billing['subtotal'],
                        'adjust_label' => $billing['adjust_label'],
                        'adjust_value' => $billing['adjust_value'],
                        'total_bill' => $billing['total_bill'],
                        'reserved_at' => (new DateTime())->format('Y-m-d H:i:s'),
                    ]);

                    if ($updated) {
                        $messages[] = 'Reservation updated successfully.';
                    } else {
                        $errors[] = 'No reservation was updated. Verify the record still exists or modify any value.';
                    }
                } catch (Throwable $t) {
                    $errors[] = 'Failed to update reservation due to billing/rate processing error.';
                }
            }
        }
    }

    $editId = (int)($_GET['edit'] ?? 0);
    if ($editId > 0) {
        $editReservation = fetchReservationById($pdo, $editId);
        if ($editReservation === null) {
            $errors[] = 'The selected reservation for editing was not found.';
        }
    }

    $listFilters = [
        'search' => $listState['search'],
        'payment_type' => $listState['payment_type'],
        'room_type' => $listState['room_type'],
        'from_date' => $listState['from_date'],
        'to_date' => $listState['to_date'],
    ];

    $totalReservations = countReservationsForFilters($pdo, $listFilters);
    $totalPages = max(1, (int)ceil($totalReservations / $listState['per_page']));
    $listState['page'] = min(max(1, $listState['page']), $totalPages);
    $reservations = fetchReservationsPage($pdo, $listFilters, $listState['page'], $listState['per_page']);

    $hasActiveFilters =
        $listState['search'] !== '' ||
        $listState['payment_type'] !== '' ||
        $listState['room_type'] !== '' ||
        $listState['from_date'] !== '' ||
        $listState['to_date'] !== '';
} catch (Throwable $e) {
    error_log('[hotel-admin] ' . $e->getMessage());

    if (isSessionStorageMode()) {
        $errors[] = 'Temporary mode is active and MySQL is not required. A runtime error occurred; check server logs for details.';
    } else {
        $errors[] = 'Database error: please verify MySQL is running and db.php settings are correct.';
    }
}

$listQuery = buildAdminListQuery($listState);
$rangeStart = $totalReservations > 0 ? (($listState['page'] - 1) * $listState['per_page']) + 1 : 0;
$rangeEnd = min($totalReservations, $listState['page'] * $listState['per_page']);
$pageStart = max(1, $listState['page'] - 2);
$pageEnd = min($totalPages, $listState['page'] + 2);
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<main>
    <section class="royal-hero admin-hero">
        <div class="royal-hero-overlay"></div>
        <div class="royal-container royal-hero-content">
            <p class="royal-kicker">OPERATIONS DESK</p>
            <h1>Royal Admin Panel</h1>
            <p class="royal-tagline">Manage reservations with precision and grace.</p>
            <p class="admin-summary">
                <?php echo (int)$totalReservations; ?> active records
                <?php if ($editReservation !== null) : ?>
                    | Editing reservation #<?php echo (int)$editReservation['id']; ?>
                <?php else : ?>
                    | Browse mode
                <?php endif; ?>
            </p>
        </div>
    </section>

    <section class="royal-section admin-main-section">
        <div class="royal-container admin-layout">
            <?php if (!empty($infoNotices)) : ?>
                <div class="success-box">
                    <?php foreach ($infoNotices as $notice) : ?>
                        <div><?php echo e($notice); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($messages)) : ?>
                <div class="success-box">
                    <?php foreach ($messages as $message) : ?>
                        <div><?php echo e($message); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)) : ?>
                <div class="error-box">
                    <?php foreach ($errors as $error) : ?>
                        <div><?php echo e($error); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($editReservation !== null) : ?>
                <section class="form-card admin-form-card">
                    <div class="royal-heading-wrap admin-heading-wrap">
                        <h2 class="royal-heading">Edit Reservation #<?php echo (int)$editReservation['id']; ?></h2>
                        <p class="royal-subhead">Adjust guest details and billing inputs.</p>
                    </div>

                    <form method="post" action="admin.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?php echo (int)$editReservation['id']; ?>">
                        <input type="hidden" name="list_context" value="1">
                        <input type="hidden" name="search" value="<?php echo e($listState['search']); ?>">
                        <input type="hidden" name="payment_type" value="<?php echo e($listState['payment_type']); ?>">
                        <input type="hidden" name="room_type" value="<?php echo e($listState['room_type']); ?>">
                        <input type="hidden" name="from_date" value="<?php echo e($listState['from_date']); ?>">
                        <input type="hidden" name="to_date" value="<?php echo e($listState['to_date']); ?>">
                        <input type="hidden" name="page" value="<?php echo (int)$listState['page']; ?>">
                        <input type="hidden" name="per_page" value="<?php echo (int)$listState['per_page']; ?>">

                        <div class="grid-2">
                            <div class="field-row">
                                <label for="customer_name">Customer Name</label>
                                <input type="text" id="customer_name" name="customer_name" value="<?php echo e((string)$editReservation['customer_name']); ?>">
                            </div>
                            <div class="field-row">
                                <label for="contact_number">Contact Number</label>
                                <input type="text" id="contact_number" name="contact_number" value="<?php echo e((string)$editReservation['contact_number']); ?>">
                            </div>
                        </div>

                        <div class="grid-2">
                            <div class="field-row">
                                <label for="from_date">From Date</label>
                                <input type="date" id="from_date" name="from_date" value="<?php echo e((string)$editReservation['from_date']); ?>">
                            </div>
                            <div class="field-row">
                                <label for="to_date">To Date</label>
                                <input type="date" id="to_date" name="to_date" value="<?php echo e((string)$editReservation['to_date']); ?>">
                            </div>
                        </div>

                        <div class="choices admin-choices">
                            <fieldset class="choice-block admin-choice-block">
                                <legend>Room Type</legend>
                                <?php foreach ($roomTypeOptions as $option) : ?>
                                    <label class="choice-item">
                                        <input type="radio" name="room_type" value="<?php echo e($option); ?>" <?php echo (string)$editReservation['room_type'] === $option ? 'checked' : ''; ?>>
                                        <?php echo e($option); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <fieldset class="choice-block admin-choice-block">
                                <legend>Room Capacity</legend>
                                <?php foreach ($capacityOptions as $option) : ?>
                                    <label class="choice-item">
                                        <input type="radio" name="room_capacity" value="<?php echo e($option); ?>" <?php echo (string)$editReservation['room_capacity'] === $option ? 'checked' : ''; ?>>
                                        <?php echo e($option); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>

                            <fieldset class="choice-block admin-choice-block">
                                <legend>Payment Type</legend>
                                <?php foreach ($paymentOptions as $option) : ?>
                                    <label class="choice-item">
                                        <input type="radio" name="payment_type" value="<?php echo e($option); ?>" <?php echo (string)$editReservation['payment_type'] === $option ? 'checked' : ''; ?>>
                                        <?php echo e($option); ?>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                        </div>

                        <div class="actions admin-actions">
                            <button type="submit">Update Reservation</button>
                            <a class="btn admin-cancel" href="admin.php?<?php echo e($listQuery); ?>">Cancel</a>
                        </div>
                    </form>
                </section>
            <?php endif; ?>

            <section class="page-card admin-table-section admin-table-card">
                <div class="royal-heading-wrap admin-heading-wrap">
                    <h2 class="royal-heading">Reservation Records</h2>
                    <p class="royal-subhead">Complete booking ledger for all guests.</p>
                </div>

                <form class="admin-table-toolbar" method="get" action="admin.php">
                    <div class="admin-toolbar-main">
                        <div class="admin-search-block">
                            <label for="admin_search">Search Guest</label>
                            <input
                                type="text"
                                id="admin_search"
                                name="search"
                                value="<?php echo e($listState['search']); ?>"
                                placeholder="Name, contact number, or ID"
                            >
                        </div>

                        <div class="admin-filter-grid">
                            <div class="admin-filter-item">
                                <label for="admin_payment_filter">Payment</label>
                                <select id="admin_payment_filter" name="payment_type">
                                    <option value="">All Payments</option>
                                    <?php foreach ($paymentOptions as $option) : ?>
                                        <option value="<?php echo e($option); ?>" <?php echo $listState['payment_type'] === $option ? 'selected' : ''; ?>>
                                            <?php echo e($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="admin-filter-item">
                                <label for="admin_room_filter">Room Type</label>
                                <select id="admin_room_filter" name="room_type">
                                    <option value="">All Rooms</option>
                                    <?php foreach ($roomTypeOptions as $option) : ?>
                                        <option value="<?php echo e($option); ?>" <?php echo $listState['room_type'] === $option ? 'selected' : ''; ?>>
                                            <?php echo e($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="admin-filter-item">
                                <label for="admin_from_filter">From Date</label>
                                <input type="date" id="admin_from_filter" name="from_date" value="<?php echo e($listState['from_date']); ?>">
                            </div>

                            <div class="admin-filter-item">
                                <label for="admin_to_filter">To Date</label>
                                <input type="date" id="admin_to_filter" name="to_date" value="<?php echo e($listState['to_date']); ?>">
                            </div>

                            <div class="admin-filter-item">
                                <label for="admin_per_page">Rows</label>
                                <select id="admin_per_page" name="per_page">
                                    <?php foreach ($perPageOptions as $option) : ?>
                                        <option value="<?php echo (int)$option; ?>" <?php echo $listState['per_page'] === $option ? 'selected' : ''; ?>>
                                            <?php echo (int)$option; ?> / page
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="admin-toolbar-actions">
                        <button type="submit">Apply Filters</button>
                        <a class="btn admin-clear-filters" href="admin.php?page=1&amp;per_page=<?php echo (int)$listState['per_page']; ?>">Clear</a>
                        <p class="admin-result-meta">
                            Showing <?php echo (int)$rangeStart; ?>-<?php echo (int)$rangeEnd; ?> of <?php echo (int)$totalReservations; ?>
                            <?php if ($hasActiveFilters) : ?>
                                <span class="admin-filter-chip">Filtered</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <input type="hidden" name="page" value="1">
                </form>

                <?php if (empty($reservations)) : ?>
                    <div class="page-card admin-empty-state">
                        <p>
                            <?php if ($hasActiveFilters) : ?>
                                No reservations matched your filters. Try widening your search criteria.
                            <?php else : ?>
                                No reservations found. New records will appear here after a booking is completed.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else : ?>
                    <div class="royal-table-wrap admin-table-wrap">
                        <table class="royal-table admin-table">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Guest</th>
                                <th>Stay</th>
                                <th>Room</th>
                                <th>Payment</th>
                                <th>Billing</th>
                                <th>Reserved At</th>
                                <th class="admin-sticky-action">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($reservations as $reservation) : ?>
                                <tr>
                                    <td><?php echo (int)$reservation['id']; ?></td>
                                    <td>
                                        <strong class="admin-guest-name"><?php echo e((string)$reservation['customer_name']); ?></strong>
                                        <span class="admin-guest-contact"><?php echo e((string)$reservation['contact_number']); ?></span>
                                    </td>
                                    <td>
                                        <span class="admin-stay-date"><?php echo e((string)$reservation['from_date']); ?></span>
                                        <span class="admin-stay-date"><?php echo e((string)$reservation['to_date']); ?></span>
                                        <span class="admin-days-chip"><?php echo (int)$reservation['no_of_days']; ?> day(s)</span>
                                    </td>
                                    <td>
                                        <span class="admin-room-type"><?php echo e((string)$reservation['room_type']); ?></span>
                                        <span class="admin-room-capacity"><?php echo e((string)$reservation['room_capacity']); ?></span>
                                    </td>
                                    <td><span class="admin-payment-pill"><?php echo e((string)$reservation['payment_type']); ?></span></td>
                                    <td class="admin-billing-cell">
                                        <div class="admin-money-row"><span>Sub</span><strong><?php echo number_format((float)$reservation['subtotal'], 2); ?></strong></div>
                                        <div class="admin-money-row"><span><?php echo e((string)$reservation['adjust_label']); ?></span><strong><?php echo number_format((float)$reservation['adjust_value'], 2); ?></strong></div>
                                        <div class="admin-money-row total"><span>Total</span><strong><?php echo number_format((float)$reservation['total_bill'], 2); ?></strong></div>
                                    </td>
                                    <td><?php echo e((string)$reservation['reserved_at']); ?></td>
                                    <td class="admin-sticky-action">
                                        <div class="admin-row-actions">
                                            <a class="btn admin-edit-link" href="admin.php?edit=<?php echo (int)$reservation['id']; ?>&amp;<?php echo e($listQuery); ?>">Edit</a>
                                            <form method="post" action="admin.php" onsubmit="return confirm('Delete this reservation?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo (int)$reservation['id']; ?>">
                                                <input type="hidden" name="list_context" value="1">
                                                <input type="hidden" name="search" value="<?php echo e($listState['search']); ?>">
                                                <input type="hidden" name="payment_type" value="<?php echo e($listState['payment_type']); ?>">
                                                <input type="hidden" name="room_type" value="<?php echo e($listState['room_type']); ?>">
                                                <input type="hidden" name="from_date" value="<?php echo e($listState['from_date']); ?>">
                                                <input type="hidden" name="to_date" value="<?php echo e($listState['to_date']); ?>">
                                                <input type="hidden" name="page" value="<?php echo (int)$listState['page']; ?>">
                                                <input type="hidden" name="per_page" value="<?php echo (int)$listState['per_page']; ?>">
                                                <button type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="admin-mobile-cards">
                        <?php foreach ($reservations as $reservation) : ?>
                            <article class="admin-reservation-card">
                                <div class="admin-card-top">
                                    <p class="admin-card-id">Reservation #<?php echo (int)$reservation['id']; ?></p>
                                    <span class="admin-payment-pill"><?php echo e((string)$reservation['payment_type']); ?></span>
                                </div>

                                <p class="admin-card-guest"><?php echo e((string)$reservation['customer_name']); ?></p>
                                <p class="admin-card-contact"><?php echo e((string)$reservation['contact_number']); ?></p>

                                <div class="admin-card-grid">
                                    <p><span>Stay</span><strong><?php echo e((string)$reservation['from_date']); ?> to <?php echo e((string)$reservation['to_date']); ?></strong></p>
                                    <p><span>Duration</span><strong><?php echo (int)$reservation['no_of_days']; ?> day(s)</strong></p>
                                    <p><span>Room</span><strong><?php echo e((string)$reservation['room_type']); ?> / <?php echo e((string)$reservation['room_capacity']); ?></strong></p>
                                    <p><span>Total Bill</span><strong><?php echo number_format((float)$reservation['total_bill'], 2); ?></strong></p>
                                    <p><span>Reserved At</span><strong><?php echo e((string)$reservation['reserved_at']); ?></strong></p>
                                </div>

                                <div class="admin-card-billing">
                                    <div><span>Subtotal</span><strong><?php echo number_format((float)$reservation['subtotal'], 2); ?></strong></div>
                                    <div><span><?php echo e((string)$reservation['adjust_label']); ?></span><strong><?php echo number_format((float)$reservation['adjust_value'], 2); ?></strong></div>
                                </div>

                                <div class="admin-row-actions admin-card-actions">
                                    <a class="btn admin-edit-link" href="admin.php?edit=<?php echo (int)$reservation['id']; ?>&amp;<?php echo e($listQuery); ?>">Edit</a>
                                    <form method="post" action="admin.php" onsubmit="return confirm('Delete this reservation?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int)$reservation['id']; ?>">
                                        <input type="hidden" name="list_context" value="1">
                                        <input type="hidden" name="search" value="<?php echo e($listState['search']); ?>">
                                        <input type="hidden" name="payment_type" value="<?php echo e($listState['payment_type']); ?>">
                                        <input type="hidden" name="room_type" value="<?php echo e($listState['room_type']); ?>">
                                        <input type="hidden" name="from_date" value="<?php echo e($listState['from_date']); ?>">
                                        <input type="hidden" name="to_date" value="<?php echo e($listState['to_date']); ?>">
                                        <input type="hidden" name="page" value="<?php echo (int)$listState['page']; ?>">
                                        <input type="hidden" name="per_page" value="<?php echo (int)$listState['per_page']; ?>">
                                        <button type="submit">Delete</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($totalPages > 1) : ?>
                        <nav class="admin-pagination" aria-label="Reservation pages">
                            <a
                                class="btn admin-page-btn <?php echo $listState['page'] <= 1 ? 'is-disabled' : ''; ?>"
                                href="admin.php?<?php echo e(buildAdminListQuery($listState, ['page' => max(1, $listState['page'] - 1)])); ?>"
                                <?php echo $listState['page'] <= 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
                            >
                                Previous
                            </a>

                            <div class="admin-page-indexes">
                                <?php for ($page = $pageStart; $page <= $pageEnd; $page++) : ?>
                                    <a
                                        class="admin-page-index <?php echo $page === $listState['page'] ? 'is-active' : ''; ?>"
                                        href="admin.php?<?php echo e(buildAdminListQuery($listState, ['page' => $page])); ?>"
                                    >
                                        <?php echo (int)$page; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>

                            <a
                                class="btn admin-page-btn <?php echo $listState['page'] >= $totalPages ? 'is-disabled' : ''; ?>"
                                href="admin.php?<?php echo e(buildAdminListQuery($listState, ['page' => min($totalPages, $listState['page'] + 1)])); ?>"
                                <?php echo $listState['page'] >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
                            >
                                Next
                            </a>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
    </section>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
