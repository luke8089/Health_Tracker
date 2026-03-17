<?php
/**
 * Appointments Page
 * Users can request appointments with connected doctors
 */

require_once __DIR__ . '/../src/helpers/Auth.php';
require_once __DIR__ . '/../src/helpers/Utils.php';
require_once __DIR__ . '/../src/models/Appointment.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requireRole('user');

$currentUser = $auth->getCurrentUser();
$appointmentModel = new Appointment();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'book_appointment') {
    $doctorId = intval($_POST['doctor_id'] ?? 0);
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $appointmentTime = trim($_POST['appointment_time'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($doctorId <= 0 || empty($appointmentDate) || empty($appointmentTime) || empty($reason)) {
        Utils::redirect('/health-tracker/public/appointments.php', 'Please fill in doctor, date, time, and reason.', 'error');
    }

    $result = $appointmentModel->createAppointment(
        $currentUser['id'],
        $doctorId,
        $appointmentDate,
        $appointmentTime,
        $reason,
        $notes
    );

    Utils::redirect(
        '/health-tracker/public/appointments.php',
        $result['message'],
        $result['success'] ? 'success' : 'error'
    );
}

$connectedDoctors = $appointmentModel->getConnectedDoctors($currentUser['id']);
$appointments = $appointmentModel->getUserAppointments($currentUser['id']);

$pendingCount = 0;
$acceptedCount = 0;
foreach ($appointments as $appointment) {
    if ($appointment['status'] === 'pending') {
        $pendingCount++;
    }
    if ($appointment['status'] === 'accepted') {
        $acceptedCount++;
    }
}

$title = 'Appointments - Health Tracker';
require_once __DIR__ . '/../src/views/partials/header.php';
?>

<div class="min-h-screen bg-gray-50" data-page="appointments">
    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl shadow-lg text-white mb-6" style="background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);">
            <div class="p-6 md:p-8">
                <h1 class="text-2xl md:text-3xl font-bold mb-2">Appointments</h1>
                <p class="text-white/90 text-sm md:text-base">Book appointments with doctors you are already connected with.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-600">Connected Doctors</p>
                <p class="text-2xl font-bold text-gray-900 mt-1"><?php echo count($connectedDoctors); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-600">Pending Requests</p>
                <p class="text-2xl font-bold text-amber-600 mt-1"><?php echo $pendingCount; ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4">
                <p class="text-sm text-gray-600">Scheduled</p>
                <p class="text-2xl font-bold text-green-600 mt-1"><?php echo $acceptedCount; ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-2xl shadow-sm p-5 md:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Book New Appointment</h2>

                    <?php if (empty($connectedDoctors)): ?>
                        <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                            You need to connect with a doctor first.
                            <a href="/health-tracker/public/connect_doctor.php" class="font-semibold underline ml-1">Connect now</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="book_appointment">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Doctor</label>
                                <select name="doctor_id" required class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-green-400 focus:border-green-400">
                                    <option value="">Select doctor</option>
                                    <?php foreach ($connectedDoctors as $doctor): ?>
                                        <option value="<?php echo (int) $doctor['id']; ?>">
                                            Dr. <?php echo htmlspecialchars($doctor['name']); ?>
                                            <?php if (!empty($doctor['specialty'])): ?>
                                                - <?php echo htmlspecialchars(ucfirst($doctor['specialty'])); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                                    <input type="date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-green-400 focus:border-green-400">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                                    <input type="time" name="appointment_time" required class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-green-400 focus:border-green-400">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                                <textarea name="reason" required rows="3" maxlength="500" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-green-400 focus:border-green-400" placeholder="What would you like to discuss?"></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Additional Notes (Optional)</label>
                                <textarea name="notes" rows="2" maxlength="500" class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-green-400 focus:border-green-400" placeholder="Any extra details"></textarea>
                            </div>

                            <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 rounded-xl text-white font-semibold bg-gradient-to-r from-gray-800 to-green-400 hover:shadow-lg transition-all duration-200">
                                Submit Appointment Request
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm p-5 md:p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">My Appointment Requests</h2>

                    <?php if (empty($appointments)): ?>
                        <div class="text-center py-10">
                            <p class="text-gray-500">No appointments yet. Book your first appointment using the form.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($appointments as $appointment): ?>
                                <?php
                                    $statusColors = [
                                        'pending' => 'bg-amber-100 text-amber-800',
                                        'accepted' => 'bg-green-100 text-green-800',
                                        'declined' => 'bg-red-100 text-red-800',
                                        'cancelled' => 'bg-gray-200 text-gray-700'
                                    ];
                                    $statusClass = $statusColors[$appointment['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <div class="border border-gray-200 rounded-xl p-4 hover:shadow-sm transition-all duration-200">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                        <div>
                                            <p class="font-semibold text-gray-900">
                                                Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                                <?php if (!empty($appointment['doctor_specialty'])): ?>
                                                    <span class="text-sm text-gray-500">• <?php echo htmlspecialchars(ucfirst($appointment['doctor_specialty'])); ?></span>
                                                <?php endif; ?>
                                            </p>
                                            <p class="text-sm text-gray-600 mt-1">
                                                <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> at <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?>
                                            </p>
                                        </div>
                                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </div>

                                    <div class="mt-3 text-sm text-gray-700">
                                        <p><span class="font-medium">Reason:</span> <?php echo nl2br(htmlspecialchars($appointment['reason'] ?? 'N/A')); ?></p>
                                        <?php if (!empty($appointment['doctor_response'])): ?>
                                            <p class="mt-2"><span class="font-medium">Doctor Response:</span> <?php echo nl2br(htmlspecialchars($appointment['doctor_response'])); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../src/views/partials/footer.php'; ?>