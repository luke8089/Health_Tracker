<?php
// Test file to check doctor update functionality
require_once __DIR__ . '/../src/helpers/Database.php';
require_once __DIR__ . '/../src/helpers/Auth.php';

session_start();

$db = (new Database())->connect();
$auth = new Auth($db);

// Check if user is logged in and is admin
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    die(json_encode(['error' => 'Not authenticated as admin']));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Doctor Update</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto" x-data="testUpdate()">
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-4">Test Doctor Update</h1>
            
            <!-- Get Doctor Info -->
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <h2 class="text-lg font-bold mb-3">Step 1: Get Doctor Info</h2>
                <div class="flex gap-3">
                    <input type="number" x-model="doctorId" placeholder="Doctor ID" 
                           class="px-4 py-2 border border-gray-300 rounded-lg">
                    <button @click="getDoctorInfo()" 
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        Get Info
                    </button>
                </div>
            </div>
            
            <!-- Doctor Data Display -->
            <div x-show="doctorData" class="mb-6 p-4 bg-green-50 rounded-lg">
                <h2 class="text-lg font-bold mb-3">Current Doctor Data:</h2>
                <pre class="bg-white p-4 rounded border text-sm overflow-auto" x-text="JSON.stringify(doctorData, null, 2)"></pre>
            </div>
            
            <!-- Update Form -->
            <div x-show="doctorData" class="mb-6 p-4 bg-yellow-50 rounded-lg">
                <h2 class="text-lg font-bold mb-3">Step 2: Update Doctor</h2>
                <form @submit.prevent="updateDoctor()" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1">Name</label>
                            <input type="text" x-model="updateData.name" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Email</label>
                            <input type="email" x-model="updateData.email" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Phone</label>
                            <input type="tel" x-model="updateData.phone"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Specialty</label>
                            <input type="text" x-model="updateData.specialty" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">License Number</label>
                            <input type="text" x-model="updateData.license_number" required
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1">Availability</label>
                            <select x-model="updateData.availability" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                                <option value="available">Available</option>
                                <option value="busy">Busy</option>
                                <option value="offline">Offline</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" :disabled="saving"
                            class="w-full px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                        <span x-text="saving ? 'Updating...' : 'Update Doctor'"></span>
                    </button>
                </form>
            </div>
            
            <!-- Response Display -->
            <div x-show="response" class="p-4 rounded-lg" :class="response.success ? 'bg-green-100' : 'bg-red-100'">
                <h2 class="text-lg font-bold mb-3" x-text="response.success ? 'Success!' : 'Error!'"></h2>
                <pre class="bg-white p-4 rounded border text-sm overflow-auto" x-text="JSON.stringify(response, null, 2)"></pre>
            </div>
            
            <!-- Console Log Display -->
            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                <h2 class="text-lg font-bold mb-3">Console Log:</h2>
                <div class="bg-white p-4 rounded border h-64 overflow-auto">
                    <template x-for="(log, index) in logs" :key="index">
                        <div class="text-sm mb-2 pb-2 border-b" :class="{
                            'text-red-600': log.type === 'error',
                            'text-blue-600': log.type === 'info',
                            'text-green-600': log.type === 'success'
                        }">
                            <span class="font-bold" x-text="log.time"></span>: 
                            <span x-text="log.message"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <script>
        function testUpdate() {
            return {
                doctorId: 15,
                doctorData: null,
                updateData: {
                    name: '',
                    email: '',
                    phone: '',
                    specialty: '',
                    license_number: '',
                    availability: 'offline'
                },
                saving: false,
                response: null,
                logs: [],

                log(message, type = 'info') {
                    const time = new Date().toLocaleTimeString();
                    this.logs.push({ time, message, type });
                    console.log(`[${type}] ${message}`);
                },

                async getDoctorInfo() {
                    this.log('Fetching doctor info for ID: ' + this.doctorId, 'info');
                    
                    try {
                        const response = await fetch(`api.php?action=get_user_details&user_id=${this.doctorId}`);
                        this.log('Response status: ' + response.status, 'info');
                        this.log('Response headers: ' + response.headers.get('content-type'), 'info');
                        
                        const text = await response.text();
                        this.log('Raw response: ' + text.substring(0, 200), 'info');
                        
                        const data = JSON.parse(text);
                        this.log('Parsed JSON successfully', 'success');
                        
                        if (data.success && data.data && data.data.user) {
                            this.doctorData = data.data.user;
                            
                            // Populate update form
                            this.updateData = {
                                name: this.doctorData.name || '',
                                email: this.doctorData.email || '',
                                phone: this.doctorData.phone || '',
                                specialty: this.doctorData.doctor_info?.specialty || '',
                                license_number: this.doctorData.doctor_info?.license_number || '',
                                availability: this.doctorData.doctor_info?.availability || 'offline'
                            };
                            
                            this.log('Doctor data loaded successfully', 'success');
                        } else {
                            this.log('API returned error: ' + data.message, 'error');
                        }
                    } catch (error) {
                        this.log('Error: ' + error.message, 'error');
                    }
                },

                async updateDoctor() {
                    this.saving = true;
                    this.response = null;
                    this.log('Starting update process...', 'info');
                    
                    try {
                        const formData = new FormData();
                        formData.append('action', 'update_doctor');
                        formData.append('doctor_id', this.doctorId);
                        formData.append('name', this.updateData.name);
                        formData.append('email', this.updateData.email);
                        formData.append('phone', this.updateData.phone);
                        formData.append('specialty', this.updateData.specialty);
                        formData.append('license_number', this.updateData.license_number);
                        formData.append('availability', this.updateData.availability);
                        
                        this.log('FormData prepared: ' + JSON.stringify(Object.fromEntries(formData)), 'info');
                        
                        const response = await fetch('doctors.php', {
                            method: 'POST',
                            body: formData
                        });
                        
                        this.log('Response status: ' + response.status, 'info');
                        this.log('Response content-type: ' + response.headers.get('content-type'), 'info');
                        
                        const text = await response.text();
                        this.log('Raw response: ' + text.substring(0, 500), 'info');
                        
                        try {
                            const data = JSON.parse(text);
                            this.response = data;
                            
                            if (data.success) {
                                this.log('Update successful!', 'success');
                                // Refresh doctor info
                                await this.getDoctorInfo();
                            } else {
                                this.log('Update failed: ' + data.message, 'error');
                            }
                        } catch (parseError) {
                            this.log('Failed to parse JSON response', 'error');
                            this.log('Parse error: ' + parseError.message, 'error');
                            this.response = { success: false, message: 'Invalid JSON response', raw: text };
                        }
                    } catch (error) {
                        this.log('Network error: ' + error.message, 'error');
                        this.response = { success: false, message: error.message };
                    } finally {
                        this.saving = false;
                    }
                }
            };
        }
    </script>
</body>
</html>
