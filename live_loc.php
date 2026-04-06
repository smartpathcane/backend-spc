<?php
/**
 * SmartPath Cane - Integrated Live Location App
 * This file handles both the tracking UI and the API endpoints.
 */

// 1. BACKEND LOGIC (API)
// -------------------------------------------------------------------------
$action = $_GET['action'] ?? null;

if ($action) {
    header('Content-Type: application/json');
    try {
        // Load Supabase Client
        $supabase = require_once __DIR__ . '/database/supabase/supabase.php';

        switch ($action) {
            case 'check_device':
                $serial = $_GET['device_serial'] ?? '';
                $result = $supabase->from('devices')->eq('device_serial', $serial)->single();
                
                if (isset($result['data']['id'])) {
                    echo json_encode(['success' => true, 'data' => $result['data']]);
                } else {
                    echo json_encode(['success' => false, 'message' => "Device not found: $serial"]);
                }
                break;

            case 'insert':
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_POST;

                $serial = $input['device_id'] ?? ''; // This is actually the serial from the frontend
                $lat = $input['latitude'] ?? null;
                $lng = $input['longitude'] ?? null;
                $accuracy = $input['accuracy'] ?? 0;

                // A. Find the integer internal ID
                $deviceResult = $supabase->from('devices')->eq('device_serial', $serial)->single();
                if (!isset($deviceResult['data']['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Device not recognized.']);
                    exit();
                }
                $internalId = $deviceResult['data']['id'];

                // B. Upsert into live_locations
                // Note: The smartpathcane SupabaseClient's upsert method handles headers automatically.
                // However, we need to pass the query string for on_conflict if using the REST API directly.
                // Our SupabaseClient wrapper might need a small adjustment if it doesn't support the URL param version.
                // Let's use the raw request for precision if needed, but let's try the upsert first.
                
                $locationData = [
                    'device_id' => $internalId,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'accuracy' => $accuracy,
                    'recorded_at' => date('c') // ISO 8601 for Postgres
                ];

                // Since we rely on the UNIQUE constraint on device_id in the DB:
                $headers = ['Prefer: resolution=merge-duplicates'];
                $upsertResult = $supabase->request('live_locations?on_conflict=device_id', 'POST', $locationData, $headers);

                if ($upsertResult['status'] >= 200 && $upsertResult['status'] < 300) {
                    // C. Sync to devices table
                    $supabase->from('devices')->eq('id', $internalId)->update([
                        'last_location_lat' => $lat,
                        'last_location_lng' => $lng,
                        'last_location_at' => date('c'),
                        'last_connected_at' => date('c')
                    ]);

                    echo json_encode(['success' => true, 'message' => 'Location synced.']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'DB Error: ' . $upsertResult['raw']]);
                }
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit();
}

// 2. FRONTEND LOGIC (UI)
// -------------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SmartPath | Tracker App</title>
    <style>
        :root {
            --bg: #ffffff;
            --text: #000000;
            --code-bg: #f0f0f0;
            --border: #000000;
        }
        * {
            box-sizing: border-box;
            border-radius: 0 !important;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .app-container {
            width: 100%;
            max-width: 400px;
            border: 2px solid var(--border);
            padding: 30px;
            background: #fff;
        }
        h1 {
            text-transform: uppercase;
            font-size: 1.5rem;
            border-bottom: 2px solid var(--border);
            padding-bottom: 15px;
            margin-top: 0;
            text-align: center;
        }
        .view {
            display: none;
        }
        .view.active {
            display: block;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            margin-bottom: 20px;
            font-family: inherit;
            font-size: 1rem;
            text-transform: uppercase;
        }
        button {
            width: 100%;
            padding: 15px;
            background-color: #000;
            color: #fff;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        button:hover {
            background-color: #333;
        }
        button:disabled {
            background-color: #ccc;
            cursor: not-allowed;
        }
        .info-card {
            border: 1px solid var(--border);
            padding: 15px;
            margin-bottom: 20px;
            background: var(--code-bg);
        }
        .label {
            font-size: 0.8rem;
            text-transform: uppercase;
            font-weight: bold;
            display: block;
            margin-bottom: 5px;
        }
        .value {
            font-size: 1.1rem;
            word-break: break-all;
        }
        #tracking-logs {
            font-size: 0.7rem;
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            margin-top: 20px;
            background:rgba(250, 250, 250, 0.97);
        }
        .log-entry {
            border-bottom: 1px solid #eee;
            padding: 2px 0;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <h1>SmartPath Cane</h1>

        <!-- SEARCH VIEW -->
        <div id="view-search" class="view active">
            <p>Connect your device serial to begin tracking on HTTPS.</p>
            <label class="label">Device Serial</label>
            <input type="text" id="serial-input" placeholder="SPC-XXXX" value="">
            <button id="btn-identify">Identify Device</button>
            <p id="search-error" style="color: red; font-size: 0.8rem; display: none; margin-top: 10px;"></p>
        </div>

        <!-- TRACKING VIEW -->
        <div id="view-track" class="view">
            <div class="info-card">
                <span class="label">Device</span>
                <span class="value" id="disp-name">Loading...</span>
            </div>

            <div id="tracking-controls">
                <button id="btn-start-track">Start Live Location</button>
                <div style="margin-top: 15px; font-size: 0.8rem; text-align: center;">
                    <input type="checkbox" id="mock-mode" style="width: auto; margin-right: 5px; margin-bottom: 0;">
                    <label for="mock-mode">Use Mock Tracking (No GPS needed)</label>
                </div>
            </div>

            <div id="tracking-active" style="display: none;">
                <div class="info-card" style="background: #000; color: #fff;">
                    <span class="label" id="tracking-label">LIVE TRACKING ACTIVE</span>
                    <div style="display: flex; justify-content: space-between; margin-top: 10px;">
                        <div>
                            <span class="label" style="font-size: 0.6rem; color: #aaa;">LAT</span>
                            <span id="curr-lat">0.0000</span>
                        </div>
                        <div>
                            <span class="label" style="font-size: 0.6rem; color: #aaa;">LNG</span>
                            <span id="curr-lng">0.0000</span>
                        </div>
                    </div>
                    <div style="margin-top: 10px; font-size: 0.7rem;">
                        <span class="label" style="font-size: 0.6rem; color: #aaa;">Last Sync</span>
                        <span id="last-sync-time">Never</span>
                    </div>
                </div>
                <button id="btn-stop-track" style="background: #fff; color: #000; border: 1px solid #000;">Stop Tracking</button>
            </div>

            <div id="tracking-logs">
                <div class="log-entry">System ready.</div>
            </div>
            
            <button id="btn-back" style="margin-top: 20px; font-size: 0.7rem; padding: 10px; background: transparent; color: #666;">Change Device</button>
        </div>
    </div>

    <script>
        const state = {
            device: null,
            watchId: null,
            mockInterval: null
        };

        const views = {
            search: document.getElementById('view-search'),
            track: document.getElementById('view-track')
        };

        const els = {
            serialInput: document.getElementById('serial-input'),
            btnIdentify: document.getElementById('btn-identify'),
            searchError: document.getElementById('search-error'),
            dispName: document.getElementById('disp-name'),
            btnStart: document.getElementById('btn-start-track'),
            btnStop: document.getElementById('btn-stop-track'),
            btnBack: document.getElementById('btn-back'),
            trackingActive: document.getElementById('tracking-active'),
            trackingControls: document.getElementById('tracking-controls'),
            currLat: document.getElementById('curr-lat'),
            currLng: document.getElementById('curr-lng'),
            lastSync: document.getElementById('last-sync-time'),
            logs: document.getElementById('tracking-logs'),
            mockMode: document.getElementById('mock-mode'),
            trackingLabel: document.getElementById('tracking-label')
        };

        function switchView(viewName) {
            Object.values(views).forEach(v => v.classList.remove('active'));
            views[viewName].classList.add('active');
        }

        function addLog(msg) {
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${msg}`;
            els.logs.prepend(entry);
        }

        els.btnIdentify.addEventListener('click', async () => {
            const serial = els.serialInput.value.trim();
            if (!serial) return;

            els.btnIdentify.disabled = true;
            els.searchError.style.display = 'none';

            try {
                const response = await fetch(`live_loc.php?action=check_device&device_serial=${encodeURIComponent(serial)}`);
                const result = await response.json();

                if (result.success) {
                    state.device = result.data;
                    els.dispName.textContent = state.device.device_name || state.device.device_serial;
                    switchView('track');
                    addLog(`Connected to ${state.device.device_serial}`);
                } else {
                    els.searchError.textContent = result.message;
                    els.searchError.style.display = 'block';
                }
            } catch (err) {
                els.searchError.textContent = "Connection error.";
                els.searchError.style.display = 'block';
            } finally {
                els.btnIdentify.disabled = false;
            }
        });

        els.btnStart.addEventListener('click', () => {
            if (els.mockMode.checked) {
                startMockTracking();
                return;
            }

            if (!navigator.geolocation) {
                addLog("Geolocation not supported.");
                return;
            }

            addLog("Activating GPS...");
            state.watchId = navigator.geolocation.watchPosition(
                async (pos) => updateUIAndSync(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy),
                (err) => {
                    addLog(`Error: ${err.message}`);
                    stopTracking();
                },
                { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
            );
        });

        function startMockTracking() {
            addLog("MOCK MODE ON");
            els.trackingLabel.textContent = "MOCK TRACKING ACTIVE";
            els.trackingControls.style.display = 'none';
            els.trackingActive.style.display = 'block';

            let mockLat = 14.5995;
            let mockLng = 120.9842;

            state.mockInterval = setInterval(() => {
                mockLat += (Math.random() - 0.5) * 0.001;
                mockLng += (Math.random() - 0.5) * 0.001;
                updateUIAndSync(mockLat, mockLng, 10);
            }, 5000);
            
            updateUIAndSync(mockLat, mockLng, 10);
        }

        function updateUIAndSync(latitude, longitude, accuracy) {
            els.currLat.textContent = latitude.toFixed(6);
            els.currLng.textContent = longitude.toFixed(6);
            els.trackingControls.style.display = 'none';
            els.trackingActive.style.display = 'block';
            syncLocation(latitude, longitude, accuracy);
        }

        async function syncLocation(lat, lng, accuracy) {
            try {
                const response = await fetch(`live_loc.php?action=insert`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        device_id: state.device.device_serial,
                        latitude: lat,
                        longitude: lng,
                        accuracy: accuracy
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    els.lastSync.textContent = new Date().toLocaleTimeString();
                    addLog(`Synced (${lat.toFixed(4)}, ${lng.toFixed(4)})`);
                } else {
                    addLog(`Sync Error: ${result.message}`);
                }
            } catch (err) {
                addLog("Sync Connection Error.");
            }
        }

        function stopTracking() {
            if (state.watchId) navigator.geolocation.clearWatch(state.watchId);
            if (state.mockInterval) clearInterval(state.mockInterval);
            state.watchId = null;
            state.mockInterval = null;
            els.trackingControls.style.display = 'block';
            els.trackingActive.style.display = 'none';
            els.trackingLabel.textContent = "LIVE TRACKING ACTIVE";
            addLog("Stopped.");
        }

        els.btnStop.addEventListener('click', stopTracking);
        els.btnBack.addEventListener('click', () => {
            stopTracking();
            switchView('search');
        });
    </script>
</body>
</html>
