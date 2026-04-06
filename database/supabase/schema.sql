-- =====================================================
-- SmartPath Cane - Supabase Database Schema
-- Run this in Supabase SQL Editor
-- =====================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- =====================================================
-- 1. USERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    auth_uid UUID REFERENCES auth.users(id) ON DELETE CASCADE,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    avatar_url TEXT,
    role VARCHAR(20) DEFAULT 'user' CHECK (role IN ('admin', 'user', 'caretaker')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'suspended')),
    email_verified_at TIMESTAMPTZ,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for users
CREATE INDEX idx_users_auth_uid ON users(auth_uid);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);

-- =====================================================
-- 2. DEVICES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS devices (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    device_serial VARCHAR(100) UNIQUE NOT NULL,
    device_name VARCHAR(100),
    device_model VARCHAR(50) DEFAULT 'SPC-001',
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    status VARCHAR(20) DEFAULT 'inactive' CHECK (status IN ('active', 'inactive', 'maintenance', 'lost')),
    firmware_version VARCHAR(20),
    battery_level INTEGER CHECK (battery_level >= 0 AND battery_level <= 100),
    last_location_lat DECIMAL(10, 8),
    last_location_lng DECIMAL(11, 8),
    last_location_at TIMESTAMPTZ,
    last_connected_at TIMESTAMPTZ,
    registered_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for devices
CREATE INDEX idx_devices_serial ON devices(device_serial);
CREATE INDEX idx_devices_user_id ON devices(user_id);
CREATE INDEX idx_devices_status ON devices(status);
CREATE INDEX idx_devices_location ON devices(last_location_lat, last_location_lng);

-- =====================================================
-- 3. LOCATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS locations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    device_id UUID NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(8, 2),
    altitude DECIMAL(10, 2),
    speed DECIMAL(8, 2),
    heading DECIMAL(6, 2),
    recorded_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for locations
CREATE INDEX idx_locations_device_id ON locations(device_id);
CREATE INDEX idx_locations_recorded_at ON locations(recorded_at);
CREATE INDEX idx_locations_coordinates ON locations(latitude, longitude);
CREATE INDEX idx_locations_device_recorded ON locations(device_id, recorded_at);

-- =====================================================
-- 4. LIVE LOCATIONS TABLE (for real-time tracking)
-- =====================================================
CREATE TABLE IF NOT EXISTS live_locations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    device_id UUID NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    accuracy DECIMAL(8, 2),
    altitude DECIMAL(10, 2),
    speed DECIMAL(8, 2),
    recorded_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(device_id)
);

-- Indexes for live_locations
CREATE INDEX idx_live_locations_device_id ON live_locations(device_id);
CREATE INDEX idx_live_locations_coordinates ON live_locations(latitude, longitude);

-- Enable RLS on live_locations
ALTER TABLE live_locations ENABLE ROW LEVEL SECURITY;

-- Policy for live_locations
CREATE POLICY "Users can view device live locations" ON live_locations
    FOR SELECT USING (device_id IN (
        SELECT id FROM devices WHERE user_id IN (
            SELECT id FROM users WHERE auth_uid = auth.uid()
        )
    ));

-- =====================================================
-- 5. SOS ALERTS TABLE
-- Location is obtained via JOIN to live_locations using device_id
-- SOS is triggered by MPU6050 sensor (fall/impact detection)
-- =====================================================
CREATE TABLE IF NOT EXISTS sos_alerts (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    device_id UUID NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    alert_type VARCHAR(20) DEFAULT 'fall' CHECK (alert_type IN ('sos', 'fall')),
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'resolved')),
    sensor_data JSONB,
    resolved_by UUID REFERENCES users(id) ON DELETE SET NULL,
    resolved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Comment for sensor_data column
COMMENT ON COLUMN sos_alerts.sensor_data IS 'MPU6050 sensor readings at time of alert (accel_x, accel_y, accel_z, gyro_x, gyro_y, gyro_z)';

-- Indexes for sos_alerts
CREATE INDEX idx_sos_device_id ON sos_alerts(device_id);
CREATE INDEX idx_sos_status ON sos_alerts(status);
CREATE INDEX idx_sos_created_at ON sos_alerts(created_at);

-- =====================================================
-- 6. GEOFENCES TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS geofences (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    device_id UUID REFERENCES devices(id) ON DELETE SET NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    center_lat DECIMAL(10, 8) NOT NULL,
    center_lng DECIMAL(11, 8) NOT NULL,
    radius_meters INTEGER NOT NULL DEFAULT 100,
    type VARCHAR(20) DEFAULT 'safe_zone' CHECK (type IN ('safe_zone', 'restricted_zone')),
    is_active BOOLEAN DEFAULT TRUE,
    notify_on_enter BOOLEAN DEFAULT FALSE,
    notify_on_exit BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for geofences
CREATE INDEX idx_geofences_user_id ON geofences(user_id);
CREATE INDEX idx_geofences_device_id ON geofences(device_id);
CREATE INDEX idx_geofences_is_active ON geofences(is_active);
CREATE INDEX idx_geofences_type ON geofences(type);

-- =====================================================
-- 7. DEVICE LOGS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS device_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    device_id UUID NOT NULL REFERENCES devices(id) ON DELETE CASCADE,
    log_type VARCHAR(20) DEFAULT 'info' CHECK (log_type IN ('info', 'warning', 'error', 'debug')),
    event VARCHAR(100) NOT NULL,
    message TEXT,
    metadata JSONB,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for device_logs
CREATE INDEX idx_device_logs_device_id ON device_logs(device_id);
CREATE INDEX idx_device_logs_log_type ON device_logs(log_type);
CREATE INDEX idx_device_logs_event ON device_logs(event);
CREATE INDEX idx_device_logs_created_at ON device_logs(created_at);

-- =====================================================
-- 8. CARETAKERS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS caretakers (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    caretaker_user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    relationship VARCHAR(50),
    permissions JSONB,
    can_view_location BOOLEAN DEFAULT TRUE,
    can_receive_alerts BOOLEAN DEFAULT TRUE,
    can_manage_device BOOLEAN DEFAULT FALSE,
    is_primary BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, caretaker_user_id)
);

-- Indexes for caretakers
CREATE INDEX idx_caretakers_user_id ON caretakers(user_id);
CREATE INDEX idx_caretakers_caretaker_user_id ON caretakers(caretaker_user_id);
CREATE INDEX idx_caretakers_is_active ON caretakers(is_active);

-- =====================================================
-- 9. NOTIFICATIONS TABLE
-- =====================================================
CREATE TABLE IF NOT EXISTS notifications (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    data JSONB,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMPTZ,
    sent_via VARCHAR(20) DEFAULT 'in_app' CHECK (sent_via IN ('push', 'sms', 'email', 'in_app')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for notifications
CREATE INDEX idx_notifications_user_id ON notifications(user_id);
CREATE INDEX idx_notifications_type ON notifications(type);
CREATE INDEX idx_notifications_is_read ON notifications(is_read);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);

-- =====================================================
-- ENABLE ROW LEVEL SECURITY (RLS)
-- =====================================================

-- Enable RLS on all tables
ALTER TABLE users ENABLE ROW LEVEL SECURITY;
ALTER TABLE devices ENABLE ROW LEVEL SECURITY;
ALTER TABLE locations ENABLE ROW LEVEL SECURITY;
ALTER TABLE sos_alerts ENABLE ROW LEVEL SECURITY;
ALTER TABLE geofences ENABLE ROW LEVEL SECURITY;
ALTER TABLE device_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE caretakers ENABLE ROW LEVEL SECURITY;
ALTER TABLE notifications ENABLE ROW LEVEL SECURITY;

-- =====================================================
-- CREATE POLICIES
-- =====================================================

-- Users: Users can only see their own data
CREATE POLICY "Users can view own profile" ON users
    FOR SELECT USING (auth.uid() = auth_uid);

CREATE POLICY "Users can update own profile" ON users
    FOR UPDATE USING (auth.uid() = auth_uid);

-- Devices: Users can only see their own devices
CREATE POLICY "Users can view own devices" ON devices
    FOR SELECT USING (user_id IN (SELECT id FROM users WHERE auth_uid = auth.uid()));

CREATE POLICY "Users can insert own devices" ON devices
    FOR INSERT WITH CHECK (user_id IN (SELECT id FROM users WHERE auth_uid = auth.uid()));

CREATE POLICY "Users can update own devices" ON devices
    FOR UPDATE USING (user_id IN (SELECT id FROM users WHERE auth_uid = auth.uid()));

-- Locations: Users can only see locations of their devices
CREATE POLICY "Users can view device locations" ON locations
    FOR SELECT USING (device_id IN (
        SELECT id FROM devices WHERE user_id IN (
            SELECT id FROM users WHERE auth_uid = auth.uid()
        )
    ));

-- SOS Alerts: Users can view alerts for their devices
CREATE POLICY "Users can view own alerts" ON sos_alerts
    FOR SELECT USING (device_id IN (
        SELECT id FROM devices WHERE user_id IN (
            SELECT id FROM users WHERE auth_uid = auth.uid()
        )
    ));

-- Geofences: Users can only see their own geofences
CREATE POLICY "Users can view own geofences" ON geofences
    FOR SELECT USING (user_id IN (SELECT id FROM users WHERE auth_uid = auth.uid()));

-- Notifications: Users can only see their own notifications
CREATE POLICY "Users can view own notifications" ON notifications
    FOR SELECT USING (user_id IN (SELECT id FROM users WHERE auth_uid = auth.uid()));

CREATE POLICY "Users can update own notifications" ON notifications
    FOR UPDATE USING (user_id IN (SELECT id FROM users WHERE auth_uid = auth.uid()));

-- =====================================================
-- CREATE FUNCTIONS FOR UPDATED_AT
-- =====================================================

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_devices_updated_at BEFORE UPDATE ON devices
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_sos_alerts_updated_at BEFORE UPDATE ON sos_alerts
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_geofences_updated_at BEFORE UPDATE ON geofences
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_caretakers_updated_at BEFORE UPDATE ON caretakers
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- =====================================================
-- SCHEMA COMPLETE
-- =====================================================
