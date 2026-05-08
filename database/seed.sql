USE lab_reservation_early;

SET NAMES utf8mb4;

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM reservation_status_history;
DELETE FROM reservations;
DELETE FROM equipment_instances;
DELETE FROM equipment_types;
DELETE FROM workstations;
DELETE FROM station_types;
DELETE FROM laboratories;
DELETE FROM student_profiles;
DELETE FROM departments;
DELETE FROM faculties;
DELETE FROM users;
DELETE FROM roles;

ALTER TABLE reservation_status_history AUTO_INCREMENT = 1;
ALTER TABLE reservations AUTO_INCREMENT = 1;
ALTER TABLE equipment_instances AUTO_INCREMENT = 1;
ALTER TABLE equipment_types AUTO_INCREMENT = 1;
ALTER TABLE workstations AUTO_INCREMENT = 1;
ALTER TABLE station_types AUTO_INCREMENT = 1;
ALTER TABLE laboratories AUTO_INCREMENT = 1;
ALTER TABLE departments AUTO_INCREMENT = 1;
ALTER TABLE faculties AUTO_INCREMENT = 1;
ALTER TABLE users AUTO_INCREMENT = 1;
ALTER TABLE roles AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO roles (role_id, role_name, description) VALUES
(1, 'student', 'Student user who can create laboratory reservations in the system'),
(2, 'admin', 'Administrator who can manage laboratories, stations, equipment, and reservations');

INSERT INTO users (
    user_id, role_id, first_name, last_name, email,
    password_hash, password_salt, phone, is_active
) VALUES
(1, 2, 'Admin',' ' ,'admin@lab.local',
 'a86f9067e8f738efc670010f9fa28eb36ad74d47b69c22f73f54f8d84bc1873e',
 'salt_admin_2026', '0370 000 0000', 1),

(2, 1, 'Onur', 'Demo', 'onur.demo@ogrenci.karabuk.edu.tr',
 '137f948fb1b97bdcefc3e2e9680f8ff32d571a60f19365ca5505ccdc9063cd38',
 'salt_student_2026', '0555 111 2233', 1),

(3, 1, 'Ayse', 'Yilmaz', 'ayse.yilmaz@ogrenci.karabuk.edu.tr',
 'e5ff025feee9daeaa07d5a8283ed30d7aa6748f9dd877bf1a8f9c03fa828494b',
 'salt_student2_2026', '0555 222 3344', 1);

INSERT INTO faculties (faculty_id, faculty_name, is_active) VALUES
(1, 'Engineering Faculty', 1),
(2, 'Technology Faculty', 1);

INSERT INTO departments (department_id, faculty_id, department_name, is_active) VALUES
(1, 1, 'Computer Engineering', 1),
(2, 1, 'Electrical and Electronics Engineering', 1),
(3, 2, 'Manufacturing Engineering', 1);

INSERT INTO student_profiles (
    user_id, student_no, faculty_id, department_id, class_year, program_type
) VALUES
(2, '2026000001', 1, 1, 2, '100% English'),
(3, '2026000002', 1, 2, 3, '100% English');

INSERT INTO laboratories (
    lab_id, department_id, lab_name, lab_code, lab_type,
    location, phone, description, is_active
) VALUES
(1, 1, 'Computer Engineering Laboratory', 'CENG-LAB', 'computer',
 'Engineering Faculty, Ground Floor', '1001',
 'Laboratory used for computer applications and programming studies.', 1),

(2, 1, 'Computer Networks Laboratory', 'NET-LAB', 'network',
 'Engineering Faculty, 1st Floor', '1002',
 'Laboratory used for network devices and computer networking applications.', 1),

(3, 2, 'Electrical and Electronics Laboratory', 'EEE-LAB', 'electronics',
 'Engineering Faculty, 2nd Floor', '1003',
 'Laboratory used for electronic circuit, measurement, and experiment applications.', 1),

(4, 3, 'Manufacturing Laboratory', 'MFG-LAB', 'machine',
 'Technology Faculty, Workshop Area', '2001',
 'Laboratory used for CNC and manufacturing applications.', 1);

INSERT INTO station_types (station_type_id, type_name, description) VALUES
(1, 'computer_desk', 'Desk type used in computer laboratories'),
(2, 'network_desk', 'Work desk that includes network devices'),
(3, 'electronics_bench', 'Electronics experiment bench'),
(4, 'machine_station', 'Machine or CNC work station'),
(5, 'general_study_desk', 'General study desk');

INSERT INTO workstations (
    station_id, lab_id, station_type_id, station_code,
    station_name, capacity, status, notes
) VALUES
(1, 1, 1, 'CENG-PC-01', 'Desk 01', 1, 'active', 'Suitable for computer applications.'),
(2, 1, 1, 'CENG-PC-02', 'Desk 02', 1, 'active', 'Suitable for computer applications.'),
(3, 1, 1, 'CENG-PC-03', 'Desk 03', 1, 'maintenance', 'Computer desk currently under maintenance.'),

(4, 2, 2, 'NET-01', 'Network Desk 01', 2, 'active', 'Network desk including router and switch devices.'),
(5, 2, 2, 'NET-02', 'Network Desk 02', 2, 'active', 'Network desk including router and switch devices.'),

(6, 3, 3, 'EEE-01', 'Electronics Experiment Bench 01', 2, 'active', 'Includes oscilloscope and power supply.'),
(7, 3, 3, 'EEE-02', 'Electronics Experiment Bench 02', 2, 'active', 'Suitable for electronic circuit experiments.'),

(8, 4, 4, 'MFG-CNC-01', 'CNC Milling Station', 1, 'active', 'Used for CNC milling applications.'),
(9, 4, 4, 'MFG-CNC-02', 'CNC Turning Station', 1, 'active', 'Used for CNC turning applications.');

INSERT INTO equipment_types (
    equipment_type_id, equipment_name, category, description
) VALUES
(1, 'Computer', 'computer', 'Laboratory computer'),
(2, 'Monitor', 'computer', 'Computer monitor'),
(3, 'Router', 'network', 'Network routing device'),
(4, 'Switch', 'network', 'Network switching device'),
(5, 'Digital Oscilloscope', 'electronics', 'Electronic measurement device'),
(6, 'DC Power Supply', 'electronics', 'Power supply used for electronics experiments'),
(7, 'CNC Milling Machine', 'machine', 'CNC milling device'),
(8, 'CNC Lathe', 'machine', 'CNC turning device');

INSERT INTO equipment_instances (
    equipment_id, equipment_type_id, lab_id, station_id,
    asset_code, brand, model, status, notes
) VALUES
(1, 1, 1, 1, 'PC-CENG-001', 'Lenovo', 'ThinkCentre', 'available', 'Computer assigned to Desk 01'),
(2, 2, 1, 1, 'MON-CENG-001', 'AOC', '24B2XH', 'available', 'Monitor assigned to Desk 01'),

(3, 1, 1, 2, 'PC-CENG-002', 'HP', 'ProDesk', 'available', 'Computer assigned to Desk 02'),
(4, 2, 1, 2, 'MON-CENG-002', 'Dell', 'P2419H', 'available', 'Monitor assigned to Desk 02'),

(5, 1, 1, 3, 'PC-CENG-003', 'Dell', 'OptiPlex', 'maintenance', 'Computer currently under maintenance'),

(6, 1, 2, 4, 'PC-NET-001', 'HP', 'ProDesk', 'available', 'Computer assigned to Network Desk 01'),
(7, 3, 2, 4, 'RTR-NET-001', 'Cisco', 'ISR-900', 'available', 'Router assigned to Network Desk 01'),
(8, 4, 2, 4, 'SWT-NET-001', 'Cisco', '2960', 'available', 'Switch assigned to Network Desk 01'),

(9, 1, 2, 5, 'PC-NET-002', 'Lenovo', 'ThinkCentre', 'available', 'Computer assigned to Network Desk 02'),
(10, 3, 2, 5, 'RTR-NET-002', 'Cisco', 'ISR-900', 'available', 'Router assigned to Network Desk 02'),
(11, 4, 2, 5, 'SWT-NET-002', 'Cisco', '2960', 'available', 'Switch assigned to Network Desk 02'),

(12, 5, 3, 6, 'OSC-EEE-001', 'Rigol', 'DS1054Z', 'available', 'Oscilloscope assigned to Electronics Experiment Bench 01'),
(13, 6, 3, 6, 'PWR-EEE-001', 'GW Instek', 'GPS-3303', 'available', 'Power supply assigned to Electronics Experiment Bench 01'),

(14, 5, 3, 7, 'OSC-EEE-002', 'Tektronix', 'TBS1052B', 'available', 'Oscilloscope assigned to Electronics Experiment Bench 02'),
(15, 6, 3, 7, 'PWR-EEE-002', 'GW Instek', 'GPS-3303', 'available', 'Power supply assigned to Electronics Experiment Bench 02'),

(16, 7, 4, 8, 'CNC-MFG-001', 'Haas', 'Mini Mill', 'available', 'CNC milling station equipment'),
(17, 8, 4, 9, 'CNC-MFG-002', 'Haas', 'ST-10', 'available', 'CNC turning station equipment');

INSERT INTO reservations (
    reservation_id, user_id, lab_id, station_id,
    start_time, end_time, purpose, status
) VALUES
(1, 2, 1, 1, '2026-05-04 10:00:00', '2026-05-04 12:00:00',
 'Programming laboratory study', 'active'),

(2, 3, 1, 2, '2026-05-04 13:00:00', '2026-05-04 15:00:00',
 'Database application study', 'active'),

(3, 2, 2, 4, '2026-05-05 09:00:00', '2026-05-05 11:00:00',
 'Computer networking router and switch practice', 'active'),

(4, 3, 3, 6, '2026-05-06 14:00:00', '2026-05-06 16:00:00',
 'Electronic circuit measurement practice', 'cancelled'),

(5, 2, 4, 8, '2026-05-07 14:00:00', '2026-05-07 16:00:00',
 'CNC milling application study', 'active');

INSERT INTO reservation_status_history (
    reservation_id, old_status, new_status, changed_by, changed_at, note
) VALUES
(1, NULL, 'active', 2, '2026-05-01 09:00:00', 'Reservation created.'),
(2, NULL, 'active', 3, '2026-05-01 09:15:00', 'Reservation created.'),
(3, NULL, 'active', 2, '2026-05-01 10:00:00', 'Reservation created.'),
(4, NULL, 'active', 3, '2026-05-01 10:30:00', 'Reservation created.'),
(4, 'active', 'cancelled', 3, '2026-05-02 12:00:00', 'Cancelled by the user.'),
(5, NULL, 'active', 2, '2026-05-02 13:00:00', 'Reservation created.');