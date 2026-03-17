<?php
/**
 * Doctor Appointments Management
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../src/models/Appointment.php';
require_once __DIR__ . '/../mail/Mailer.php';
require_once __DIR__ . '/../src/helpers/Utils.php';

$appointmentModel = new Appointment();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_appointment') {
    $appointmentId = intval($_POST['appointment_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');
    $doctorResponse = trim($_POST['doctor_response'] ?? '');

    $updateResult = $appointmentModel->updateStatus($appointmentId, $currentUser['id'], $status, $doctorResponse);

    if ($updateResult['success'] && $status === 'accepted') {
        $appointment = $appointmentModel->getAppointmentWithParties($appointmentId);

        if ($appointment && !empty($appointment['patient_email'])) {
            $mailer = new Mailer();
            $emailResult = $mailer->sendAppointmentScheduledEmail(
                [
                    'name' => $appointment['patient_name'],
                    'email' => $appointment['patient_email']
                ],
                [
                    'name' => $appointment['doctor_name'],
                    'specialty' => $appointment['doctor_specialty'] ?? ''
                ],
                [
                    'appointment_date' => $appointment['appointment_date'],
                    'appointment_time' => $appointment['appointment_time'],
                    'doctor_response' => $appointment['doctor_response'] ?? '',
                    'reason' => $appointment['reason'] ?? ''
                ]
            );

            if (!$emailResult['success']) {
                Utils::redirect(doctorUrl('appointments.php'), 'Appointment accepted, but email notification could not be sent.', 'error');
            }
        }
    }

    Utils::redirect(
        doctorUrl('appointments.php'),
        $updateResult['message'],
        $updateResult['success'] ? 'success' : 'error'
    );
}

$pendingAppointments = $appointmentModel->getDoctorAppointments($currentUser['id'], 'pending');
$acceptedAppointments = $appointmentModel->getDoctorAppointments($currentUser['id'], 'accepted');
$declinedAppointments = $appointmentModel->getDoctorAppointments($currentUser['id'], 'declined');

$title = setDoctorPageTitle('Appointments');
require_once __DIR__ . '/includes/header.php';
?>

<div class="min-h-screen">
    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="rounded-2xl shadow-xl text-white mb-8" style="background: linear-gradient(135deg, #1C2529 0%, #A1D1B1 100%);">
            <div class="p-8">
                <h1 class="text-3xl font-bold mb-2">Appointment Requests</h1>
                <p class="text-white/90">Review patient requests and schedule appointments.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-md p-4">
                <p class="text-sm text-gray-600">Pending</p>
                <p class="text-2xl font-bold text-amber-600"><?php echo count($pendingAppointments); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4">
                <p class="text-sm text-gray-600">Accepted</p>
                <p class="text-2xl font-bold text-green-600"><?php echo count($acceptedAppointments); ?></p>
            </div>
            <div class="bg-white rounded-xl shadow-md p-4">
                <p class="text-sm text-gray-600">Declined</p>
                <p class="text-2xl font-bold text-red-600"><?php echo count($declinedAppointments); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-xl font-bold text-gray-900 mb-5">Pending Requests</h2>

                    <?php if (empty($pendingAppointments)): ?>
                        <p class="text-gray-500">No pending appointment requests.</p>
                    <?php else: ?>
                        <div class="space-y-5">
                            <?php foreach ($pendingAppointments as $appointment): ?>
                                <div class="border border-gray-200 rounded-xl p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-3">
                                        <div>
                                            <h3 class="font-semibold text-gray-900"><?php echo htmlspecialchars($appointment['patient_name']); ?></h3>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($appointment['patient_email']); ?></p>
                                            <?php if (!empty($appointment['patient_phone'])): ?>
                                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['patient_phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-700">
                                            <p class="font-medium"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></p>
                                            <p><?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                                        </div>
                                    </div>

                                    <div class="text-sm text-gray-700 mb-4">
                                        <p><span class="font-medium">Reason:</span> <?php echo nl2br(htmlspecialchars($appointment['reason'] ?? 'N/A')); ?></p>
                                        <?php if (!empty($appointment['notes'])): ?>
                                            <p class="mt-2"><span class="font-medium">Patient Notes:</span> <?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                                        <?php endif; ?>
                                    </div>

                                    <form method="POST" class="space-y-3">
                                        <input type="hidden" name="action" value="update_appointment">
                                        <input type="hidden" name="appointment_id" value="<?php echo (int) $appointment['id']; ?>">

                                        <textarea name="doctor_response" rows="2" maxlength="500" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-400 focus:border-green-400" placeholder="Optional response to patient"></textarea>

                                        <div class="flex flex-wrap gap-2">
                                            <button type="submit" name="status" value="accepted" class="inline-flex items-center px-4 py-2 rounded-lg text-white bg-green-600 hover:bg-green-700 transition-colors">
                                                Accept & Schedule
                                            </button>
                                            <button type="submit" name="status" value="declined" class="inline-flex items-center px-4 py-2 rounded-lg text-white bg-red-600 hover:bg-red-700 transition-colors">
                                                Decline
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Accepted</h2>
                    <?php if (empty($acceptedAppointments)): ?>
                        <p class="text-sm text-gray-500">No accepted appointments yet.</p>
                    <?php else: ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            <?php foreach ($acceptedAppointments as $appointment): ?>
                                <div class="border border-green-200 bg-green-50 rounded-lg p-3">
                                    <p class="font-medium text-sm text-gray-900"><?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> • <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bg-white rounded-xl shadow-md p-6">
                    <h2 class="text-lg font-bold text-gray-900 mb-4">Declined</h2>
                    <?php if (empty($declinedAppointments)): ?>
                        <p class="text-sm text-gray-500">No declined appointments.</p>
                    <?php else: ?>
                        <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            <?php foreach ($declinedAppointments as $appointment): ?>
                                <div class="border border-red-200 bg-red-50 rounded-lg p-3">
                                    <p class="font-medium text-sm text-gray-900"><?php echo htmlspecialchars($appointment['patient_name']); ?></p>
                                    <p class="text-xs text-gray-600 mt-1"><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?> • <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</main>
</body>
</html>