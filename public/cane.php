<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>SmartPath Cane - Device</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1e3a5f 0%, #0f172a 100%);
            min-height: 100vh;
            color: white;
            display: flex;
            flex-direction: column;
        }
        
        .header {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .header h1 {
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .status-bar {
            display: flex;
            justify-content: space-around;
            padding: 1rem;
            background: rgba(0,0,0,0.2);
        }
        
        .status-item {
            text-align: center;
        }
        
        .status-label {
            font-size: 0.7rem;
            color: #94a3b8;
            text-transform: uppercase;
        }
        
        .status-value {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 0.25rem;
        }
        
        .status-online {
            color: #10b981;
        }
        
        .status-offline {
            color: #ef4444;
        }
        
        .main-content {
            flex: 1;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .card {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .card-title {
            font-size: 0.8rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
        }
        
        .location-display {
            font-family: 'Courier New', monospace;
            font-size: 1.1rem;
            word-break: break-all;
        }
        
        .sos-button {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            border: none;
            border-radius: 50%;
            width: 120px;
            height: 120px;
            margin: 1rem auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(220, 38, 38, 0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .sos-button:active {
            transform: scale(0.95);
            box-shadow: 0 2px 10px rgba(220, 38, 38, 0.4);
        }
        
        .sos-button .sos-icon {
            font-size: 2rem;
            margin-bottom: 0.25rem;
        }
        
        .log-container {
            flex: 1;
            overflow-y: auto;
            max-height: 200px;
        }
        
        .log-entry {
            font-size: 0.75rem;
            padding: 0.25rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            color: #94a3b8;
        }
        
        .log-entry.success {
            color: #10b981;
        }
        
        .log-entry.error {
            color: #ef4444;
        }
        
        .settings-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .settings-row:last-child {
            border-bottom: none;
        }
        
        .toggle {
            width: 50px;
            height: 28px;
            background: #475569;
            border-radius: 14px;
            position: relative;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .toggle.active {
            background: #10b981;
        }
        
        .toggle::after {
            content: '';
            position: absolute;
            width: 24px;
            height: 24px;
            background: white;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: transform 0.3s;
        }
        
        .toggle.active::after {
            transform: translateX(22px);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🦯 SmartPath Cane</h1>
    </div>
    
    <div class="status-bar">
        <div class="status-item">
            <div class="status-label">Status</div>
            <div class="status-value status-online" id="connection-status">● Online</div>
        </div>
        <div class="status-item">
            <div class="status-label">Battery</div>
            <div class="status-value" id="battery-level">85%</div>
        </div>
        <div class="status-item">
            <div class="status-label">Device ID</div>
            <div class="status-value" id="device-id">SPC-001</div>
        </div>
    </div>
    
    <div class="main-content">
        <div class="card">
            <div class="card-title">Current Location</div>
            <div class="location-display" id="location-display">
                Tap "Start Tracking" to begin GPS tracking
            </div>
            <button id="start-tracking-btn" style="margin-top: 1rem; padding: 0.75rem 1.5rem; background: #10b981; border: none; border-radius: 8px; color: white; font-weight: 600; cursor: pointer; width: 100%;">
                📍 Start Tracking
            </button>
        </div>
        
        <button class="sos-button" id="sos-button">
            <span class="sos-icon">🚨</span>
            <span>SOS</span>
        </button>
        
        <div class="card">
            <div class="card-title">Settings</div>
            <div class="settings-row">
                <span>Live Tracking</span>
                <div class="toggle active" id="tracking-toggle"></div>
            </div>
            <div class="settings-row">
                <span>Update Interval</span>
                <span>1 second</span>
            </div>
        </div>
        
        <div class="card">
            <div class="card-title">Activity Log</div>
            <div class="log-container" id="log-container">
                <div class="log-entry">Device initialized...</div>
            </div>
        </div>
    </div>

    <script>
        // Device Configuration - BUILT-IN DEVICE ID
        const DEVICE_CONFIG = {
            device_serial: 'SPC-DEV-001',
            device_name: 'Test Cane Device',
            api_url: window.location.hostname === 'localhost' 
                ? '/smartpathcane/backend-spc/public'
                : '/backend-spc/public'
        };
        
        // User authentication token (if available)
        let authToken = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
        
        // Set auth token in API calls if available
        if (authToken) {
            // Add token to future requests
            fetch(`${DEVICE_CONFIG.api_url}/api/auth/me`, {
                headers: {
                    'Authorization': `Bearer ${authToken}`
                }
            })
            .then(response => response.json())
            .then(userData => {
                if (userData.success) {
                    addLog('User authenticated: ' + (userData.data.first_name || userData.data.email), 'success');
                }
            })
            .catch(err => {
                console.log('Auth check failed, token may be expired');
                // Clear invalid token
                localStorage.removeItem('auth_token');
                sessionStorage.removeItem('auth_token');
                authToken = null;
            });
        }
        
        // State
        let isTracking = true;
        let updateInterval = null;
        let currentPosition = null;
        
        // DOM Elements
        const locationDisplay = document.getElementById('location-display');
        const connectionStatus = document.getElementById('connection-status');
        const logContainer = document.getElementById('log-container');
        const trackingToggle = document.getElementById('tracking-toggle');
        const sosButton = document.getElementById('sos-button');
        
        // Add log entry
        function addLog(message, type = 'info') {
            const entry = document.createElement('div');
            entry.className = `log-entry ${type}`;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            logContainer.insertBefore(entry, logContainer.firstChild);
            
            // Keep only last 20 entries
            while (logContainer.children.length > 20) {
                logContainer.removeChild(logContainer.lastChild);
            }
        }
        
        // Store last sent position to avoid duplicates
        let lastSentPosition = null;
        
        // Update location to server (UPSERT - only one row per device)
        async function updateLocation(position) {
            if (!isTracking) return;
            
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            
            // Check if position has changed (avoid duplicates)
            if (lastSentPosition) {
                const latDiff = Math.abs(lat - lastSentPosition.lat);
                const lngDiff = Math.abs(lng - lastSentPosition.lng);
                
                // Only update if moved more than 0.00001 degrees (about 1 meter)
                if (latDiff < 0.00001 && lngDiff < 0.00001) {
                    addLog('Position unchanged, skipping update', 'info');
                    return;
                }
            }
            
            const data = {
                device_serial: DEVICE_CONFIG.device_serial,
                latitude: lat,
                longitude: lng,
                accuracy: position.coords.accuracy || null,
                altitude: position.coords.altitude || null,
                speed: position.coords.speed || null,
                battery_level: Math.floor(Math.random() * 30) + 70 // Simulated 70-100%
            };
            
            try {
                const headers = {
                    'Content-Type': 'application/json'
                };
                
                // Include auth token if available
                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                }
                
                const response = await fetch(`${DEVICE_CONFIG.api_url}/api/cane/location`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    connectionStatus.textContent = '● Online';
                    connectionStatus.className = 'status-value status-online';
                    addLog('Location updated', 'success');
                    
                    // Store this position as last sent
                    lastSentPosition = { lat, lng };
                } else {
                    throw new Error(result.error || 'Update failed');
                }
            } catch (error) {
                connectionStatus.textContent = '● Offline';
                connectionStatus.className = 'status-value status-offline';
                addLog(`Error: ${error.message}`, 'error');
                console.error('Update location error:', error);
            }
        }
        
        // Play SOS alert sound
        function playSOSAlert() {
            // Try to play the alarm sound file first
            try {
                // Adjust path based on where this file is served from
                const audio = new Audio('../frontend-spc/assets/audio/alarm.mp3');
                audio.volume = 0.7;
                audio.play().catch(e => {
                    console.warn('MP3 audio play failed, using fallback:', e);
                    // Use fallback sound if MP3 fails
                    playFallbackSOSAlert();
                });
            } catch (e) {
                console.warn('MP3 audio creation failed, using fallback:', e);
                playFallbackSOSAlert();
            }
        }
        
        // Fallback SOS alert sound (generated)
        function playFallbackSOSAlert() {
            try {
                const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioContext.createOscillator();
                const gainNode = audioContext.createGain();
                
                oscillator.connect(gainNode);
                gainNode.connect(audioContext.destination);
                
                // Siren-like sound pattern
                oscillator.type = 'sawtooth';
                oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
                oscillator.frequency.exponentialRampToValueAtTime(400, audioContext.currentTime + 0.5);
                oscillator.frequency.exponentialRampToValueAtTime(800, audioContext.currentTime + 1);
                
                gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 1);
                
                oscillator.start(audioContext.currentTime);
                oscillator.stop(audioContext.currentTime + 1);
                
                // Play second beep
                setTimeout(() => {
                    const osc2 = audioContext.createOscillator();
                    const gain2 = audioContext.createGain();
                    osc2.connect(gain2);
                    gain2.connect(audioContext.destination);
                    osc2.type = 'sawtooth';
                    osc2.frequency.setValueAtTime(800, audioContext.currentTime);
                    osc2.frequency.exponentialRampToValueAtTime(400, audioContext.currentTime + 0.5);
                    osc2.frequency.exponentialRampToValueAtTime(800, audioContext.currentTime + 1);
                    gain2.gain.setValueAtTime(0.3, audioContext.currentTime);
                    gain2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 1);
                    osc2.start(audioContext.currentTime);
                    osc2.stop(audioContext.currentTime + 1);
                }, 1200);
                
            } catch (e) {
                console.error('Fallback audio play failed:', e);
            }
        }
        
        // Send SOS Alert
        async function sendSOS() {
            if (!currentPosition) {
                alert('GPS not available yet. Please wait...');
                return;
            }
            
            // Play alert sound immediately
            playSOSAlert();
            
            if (!confirm('Send SOS Emergency Alert?')) return;
            
            const data = {
                device_serial: DEVICE_CONFIG.device_serial,
                alert_type: 'SOS',
                latitude: currentPosition.coords.latitude,
                longitude: currentPosition.coords.longitude,
                message: 'Emergency button pressed'
            };
            
            try {
                sosButton.disabled = true;
                sosButton.innerHTML = '<span class="sos-icon">⏳</span><span>Sending...</span>';
                
                const headers = {
                    'Content-Type': 'application/json'
                };
                
                // Include auth token if available
                if (authToken) {
                    headers['Authorization'] = `Bearer ${authToken}`;
                }
                
                const response = await fetch(`${DEVICE_CONFIG.api_url}/api/cane/sos`, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    addLog('SOS Alert sent!', 'success');
                    alert('SOS Alert sent successfully!');
                } else {
                    throw new Error(result.error);
                }
            } catch (error) {
                addLog(`SOS Failed: ${error.message}`, 'error');
                alert('Failed to send SOS. Please try again.');
            } finally {
                sosButton.disabled = false;
                sosButton.innerHTML = '<span class="sos-icon">🚨</span><span>SOS</span>';
            }
        }
        
        // Handle position update
        function onPositionUpdate(position) {
            currentPosition = position;
            
            const lat = position.coords.latitude.toFixed(6);
            const lng = position.coords.longitude.toFixed(6);
            const accuracy = Math.round(position.coords.accuracy);
            
            locationDisplay.innerHTML = `
                Lat: ${lat}<br>
                Lng: ${lng}<br>
                Accuracy: ±${accuracy}m
            `;
            
            // Update server
            updateLocation(position);
        }
        
        // Handle position error
        function onPositionError(error) {
            let message = 'GPS Error: ';
            switch(error.code) {
                case error.PERMISSION_DENIED:
                    message += 'Permission denied';
                    break;
                case error.POSITION_UNAVAILABLE:
                    message += 'Position unavailable';
                    break;
                case error.TIMEOUT:
                    message += 'Timeout';
                    break;
                default:
                    message += 'Unknown error';
            }
            locationDisplay.textContent = message;
            addLog(message, 'error');
        }
        
        // Request location permission
        async function requestLocationPermission() {
            addLog('Requesting location permission...');
            
            if (!navigator.geolocation) {
                locationDisplay.textContent = 'GPS not supported on this device';
                addLog('GPS not supported', 'error');
                alert('Your device does not support GPS tracking');
                return false;
            }
            
            // Try to get position once to trigger permission prompt
            return new Promise((resolve) => {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        addLog('Location permission granted!', 'success');
                        resolve(true);
                    },
                    (error) => {
                        if (error.code === error.PERMISSION_DENIED) {
                            locationDisplay.textContent = 'Location permission denied';
                            addLog('Location permission denied by user', 'error');
                            alert('Please allow location access in your browser settings to use this app.');
                        } else {
                            addLog('Location error: ' + error.message, 'error');
                        }
                        resolve(false);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            });
        }
        
        // Start tracking
        async function startTracking() {
            // Request permission first
            const hasPermission = await requestLocationPermission();
            if (!hasPermission) {
                return;
            }
            
            addLog('Starting GPS tracking...');
            
            // Get position every second
            updateInterval = setInterval(() => {
                navigator.geolocation.getCurrentPosition(
                    onPositionUpdate,
                    onPositionError,
                    {
                        enableHighAccuracy: true,
                        timeout: 5000,
                        maximumAge: 0
                    }
                );
            }, 1000);
        }
        
        // Stop tracking
        function stopTracking() {
            if (updateInterval) {
                clearInterval(updateInterval);
                updateInterval = null;
            }
            addLog('Tracking stopped');
        }
        
        // Toggle tracking
        trackingToggle.addEventListener('click', () => {
            isTracking = !isTracking;
            trackingToggle.classList.toggle('active');
            
            if (isTracking) {
                startTracking();
            } else {
                stopTracking();
            }
        });
        
        // SOS button
        sosButton.addEventListener('click', sendSOS);
        
        // Start tracking button (for mobile permission)
        const startBtn = document.getElementById('start-tracking-btn');
        startBtn.addEventListener('click', () => {
            startBtn.style.display = 'none';
            startTracking();
        });
        
        // Initialize
        document.getElementById('device-id').textContent = DEVICE_CONFIG.device_serial;
        addLog(`Device: ${DEVICE_CONFIG.device_serial}`);
        addLog('Tap "Start Tracking" to begin');
    </script>
</body>
</html>
