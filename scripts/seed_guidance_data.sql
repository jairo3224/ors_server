-- ============================================================
-- Seed data for Guidance Dashboard demo
-- Run after the main schema.sql has been imported
-- Database: ors_db
-- ============================================================

-- --------------------------------------------------------
-- More students across different departments
-- --------------------------------------------------------
INSERT IGNORE INTO `students` (`id`, `student_number`, `first_name`, `last_name`, `middle_name`, `year_level`, `department_id`, `section_id`, `status`, `deleted_at`) VALUES
-- College of Engineering (dept 2)
(17, 'STU-2024-017', 'Angelo', 'Dela Cruz', 'M.', 2, 2, 1, 'active', NULL),
(18, 'STU-2024-018', 'Bea', 'Mendoza', 'R.', 3, 2, 1, 'active', NULL),
-- College of Business (dept 3)
(19, 'STU-2024-019', 'Carlo', 'Villanueva', 'S.', 2, 3, 1, 'active', NULL),
(20, 'STU-2024-020', 'Diana', 'Garcia', 'T.', 3, 3, 1, 'active', NULL),
-- College of Education (dept 4)
(21, 'STU-2024-021', 'Ethan', 'Reyes', 'L.', 2, 4, 1, 'active', NULL),
(22, 'STU-2024-022', 'Faith', 'Torres', 'P.', 3, 4, 1, 'active', NULL),
-- College of Arts and Sciences (dept 5)
(23, 'STU-2024-023', 'Gabriel', 'Flores', 'C.', 1, 5, 1, 'active', NULL),
(24, 'STU-2024-024', 'Hannah', 'Advincula', 'B.', 2, 5, 1, 'active', NULL);

-- --------------------------------------------------------
-- More incident types if missing
-- --------------------------------------------------------
INSERT IGNORE INTO `incident_types` (`id`, `type_name`, `default_severity`) VALUES
(6, 'Anxiety / Depression', 'high'),
(7, 'Family Concern', 'medium'),
(8, 'Academic Performance', 'low'),
(9, 'Peer Conflict', 'medium'),
(10, 'Substance Concern', 'critical'),
(11, 'Attendance / Tardiness', 'low'),
(12, 'Self-Harm Risk', 'critical');

-- --------------------------------------------------------
-- More sections for other departments
-- --------------------------------------------------------
INSERT IGNORE INTO `sections` (`id`, `section_name`, `year_level`, `department_id`, `deleted_at`) VALUES
(4, 'BSME-2A', 2, 2, NULL),
(5, 'BSCE-3A', 3, 2, NULL),
(6, 'BSBA-2A', 2, 3, NULL),
(7, 'BSEd-2A', 2, 4, NULL),
(8, 'AB-1A', 1, 5, NULL);

-- --------------------------------------------------------
-- Incident reports assigned TO Guidance (user id = 5)
-- --------------------------------------------------------
INSERT IGNORE INTO `incident_reports` (`id`, `report_code`, `student_id`, `reported_by`, `assigned_to`, `incident_type_id`, `description`, `urgency_level`, `current_status`, `created_at`) VALUES
(18, 'INC-1780500001-ABCD1', 1, 8, 5, 6, 'Student has been showing signs of severe anxiety during exams. Frequently crying in class and isolating from peers.', 'high', 'under_review', '2026-06-03 08:00:00'),
(19, 'INC-1780500002-ABCD2', 8, 8, 5, 9, 'Caught in a heated argument with a classmate that nearly turned physical. Both parties were sent to the guidance office.', 'medium', 'in_progress', '2026-06-03 09:30:00'),
(20, 'INC-1780500003-ABCD3', 12, 8, 5, 7, 'Student has been frequently absent due to ongoing family issues. Grades are dropping significantly.', 'medium', 'under_review', '2026-06-04 07:45:00'),
(21, 'INC-1780500004-ABCD4', 19, 7, 5, 8, 'Student failed 3 major exams this semester. Consistently not submitting requirements. Needs academic intervention.', 'low', 'under_review', '2026-06-04 10:00:00'),
(22, 'INC-1780500005-ABCD5', 20, 7, 5, 6, 'Student expressed feelings of hopelessness and lack of motivation during class sharing activity.', 'high', 'reported', '2026-06-05 08:15:00'),
(23, 'INC-1780500006-ABCD6', 4, 8, 5, 12, 'Student was found with markings on arm. Classmate reported seeing self-harm scars. Immediate intervention needed.', 'critical', 'in_progress', '2026-06-05 09:00:00'),
(24, 'INC-1780500007-ABCD7', 14, 8, NULL, 11, 'Student has been late to class 12 times this month. No valid excuse provided.', 'low', 'reported', '2026-06-05 11:00:00'),
(25, 'INC-1780500008-ABCD8', 5, 8, NULL, 5, 'Student was caught cheating during a quiz. Used unauthorized notes on phone.', 'medium', 'reported', '2026-06-05 13:30:00');

-- --------------------------------------------------------
-- Referrals TO Guidance Office (inbox for user id = 5)
-- --------------------------------------------------------
INSERT IGNORE INTO `referrals` (`id`, `incident_id`, `referred_to`, `referred_by`, `remarks`, `status`, `referred_at`) VALUES
(4, 18, 5, 8, 'Student needs professional counseling for anxiety issues. Requesting assessment.', 'pending', '2026-06-03 08:15:00'),
(5, 21, 5, 7, 'Student needs academic counseling and possible intervention plan.', 'pending', '2026-06-04 10:15:00'),
(6, 22, 5, 7, 'Student showing signs of depression. Requesting immediate assessment.', 'pending', '2026-06-05 08:30:00'),
(7, 23, 5, 8, 'URGENT: Student may be self-harming. Needs immediate counseling intervention.', 'pending', '2026-06-05 09:15:00'),
(8, 24, 5, 8, 'Chronic tardiness affecting academic performance. Request guidance intervention.', 'pending', '2026-06-05 11:15:00');

-- --------------------------------------------------------
-- Referrals FROM Guidance Office (sent by user id = 5)
-- --------------------------------------------------------
INSERT IGNORE INTO `referrals` (`id`, `incident_id`, `referred_to`, `referred_by`, `remarks`, `status`, `referred_at`) VALUES
(9, 19, 6, 5, 'Student may benefit from spiritual counseling regarding peer relationships.', 'pending', '2026-06-03 10:00:00'),
(10, 20, 1, 5, 'Family situation may require social welfare assistance. OSAS to handle.', 'pending', '2026-06-04 08:30:00'),
(11, 25, 1, 5, 'Cheating incident needs formal disciplinary action from OSAS.', 'pending', '2026-06-05 14:00:00');

-- --------------------------------------------------------
-- Responses / Counseling Notes by Guidance (user id = 5)
-- --------------------------------------------------------

-- Need to insert into response_types first (already exists from schema)
-- response_types: 1=assessment, 2=counseling_note, 3=recommendation, 4=remark, 5=resolution, 6=parent_meeting

INSERT IGNORE INTO `responses` (`id`, `incident_id`, `user_id`, `response_type_id`, `remarks`, `created_at`) VALUES
-- Counseling session notes for incident 18 (anxiety)
(1, 18, 5, 2, '{"title":"Initial Counseling Session","date":"2026-06-04","time":"10:00 AM","location":"Guidance Office","agenda":"Assess anxiety triggers and coping mechanisms. Student agreed to weekly sessions."}', '2026-06-04 11:00:00'),
(2, 18, 5, 1, 'Student shows moderate to severe test anxiety. Recommended referral to psychiatrist for further evaluation. Recommended accommodations: separate testing room, extended time.', '2026-06-04 11:30:00'),

-- Parent meeting for incident 19 (peer conflict)
(3, 19, 5, 6, '{"title":"Parent Meeting - Peer Conflict","date":"2026-06-04","time":"2:00 PM","location":"Guidance Office","agenda":"Discussed conflict with parents of both students. Both agreed to mediation session next week."}', '2026-06-04 15:00:00'),

-- Assessment for incident 20 (family issues)
(4, 20, 5, 1, 'Student is dealing with parental separation at home. Grades declining as a direct result. Recommending weekly check-ins and referral to OSAS for family assistance.', '2026-06-04 09:00:00'),

-- Counseling for incident 23 (self-harm risk)
(5, 23, 5, 2, '{"title":"Emergency Counseling Session","date":"2026-06-05","time":"9:30 AM","location":"Guidance Office","agenda":"Immediate risk assessment conducted. Student is stable but requires close monitoring. Parents notified. Referral to clinical psychologist initiated."}', '2026-06-05 11:00:00'),

-- Assessment for incident 22 (depression)
(6, 22, 5, 1, 'Initial assessment indicates mild to moderate depression. Recommended: regular counseling sessions, parental involvement, and monitoring by class adviser.', '2026-06-05 10:00:00'),

-- Recommendation for academic intervention (incident 21)
(7, 21, 5, 3, 'Student to be placed on academic probation with weekly progress monitoring. Required to attend study skills workshop and meet with academic adviser twice a month.', '2026-06-05 11:30:00'),

-- Parent meeting for incident 23 (self-harm)
(8, 23, 5, 6, '{"title":"Emergency Parent Conference","date":"2026-06-05","time":"2:00 PM","location":"Guidance Office","agenda":"Discussed immediate safety plan with parents. Student to undergo professional psychological evaluation. School to provide support accommodations."}', '2026-06-05 15:30:00');

-- --------------------------------------------------------
-- Responses / Counseling Notes by Guidance (user id = 5)
-- --------------------------------------------------------

-- Need to insert into response_types first (already exists from schema)
-- response_types: 1=assessment, 2=counseling_note, 3=recommendation, 4=remark, 5=resolution, 6=parent_meeting

INSERT IGNORE INTO `responses` (`id`, `incident_id`, `user_id`, `response_type_id`, `remarks`, `created_at`) VALUES
-- Counseling session notes for incident 18 (anxiety)
(1, 18, 5, 2, '{"title":"Initial Counseling Session","date":"2026-06-04","time":"10:00 AM","location":"Guidance Office","agenda":"Assess anxiety triggers and coping mechanisms. Student agreed to weekly sessions."}', '2026-06-04 11:00:00'),
(2, 18, 5, 1, 'Student shows moderate to severe test anxiety. Recommended referral to psychiatrist for further evaluation. Recommended accommodations: separate testing room, extended time.', '2026-06-04 11:30:00'),

-- Parent meeting for incident 19 (peer conflict)
(3, 19, 5, 6, '{"title":"Parent Meeting - Peer Conflict","date":"2026-06-04","time":"2:00 PM","location":"Guidance Office","agenda":"Discussed conflict with parents of both students. Both agreed to mediation session next week."}', '2026-06-04 15:00:00'),

-- Assessment for incident 20 (family issues)
(4, 20, 5, 1, 'Student is dealing with parental separation at home. Grades declining as a direct result. Recommending weekly check-ins and referral to OSAS for family assistance.', '2026-06-04 09:00:00'),

-- Counseling for incident 23 (self-harm risk)
(5, 23, 5, 2, '{"title":"Emergency Counseling Session","date":"2026-06-05","time":"9:30 AM","location":"Guidance Office","agenda":"Immediate risk assessment conducted. Student is stable but requires close monitoring. Parents notified. Referral to clinical psychologist initiated."}', '2026-06-05 11:00:00'),

-- Assessment for incident 22 (depression)
(6, 22, 5, 1, 'Initial assessment indicates mild to moderate depression. Recommended: regular counseling sessions, parental involvement, and monitoring by class adviser.', '2026-06-05 10:00:00'),

-- Recommendation for academic intervention (incident 21)
(7, 21, 5, 3, 'Student to be placed on academic probation with weekly progress monitoring. Required to attend study skills workshop and meet with academic adviser twice a month.', '2026-06-05 11:30:00'),

-- Parent meeting for incident 23 (self-harm)
(8, 23, 5, 6, '{"title":"Emergency Parent Conference","date":"2026-06-05","time":"2:00 PM","location":"Guidance Office","agenda":"Discussed immediate safety plan with parents. Student to undergo professional psychological evaluation. School to provide support accommodations."}', '2026-06-05 15:30:00');

-- --------------------------------------------------------
-- Notifications for Guidance user (id = 5)
-- --------------------------------------------------------
INSERT IGNORE INTO `notifications` (`id`, `user_id`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 5, 'New Referral Received', 'Teacher Christian Reyes referred a student (Juan Dela Cruz) for anxiety counseling.', '/guidance/inbox', 0, '2026-06-03 08:15:00'),
(2, 5, 'New Referral Received', 'Department Head Elena Cruz referred a student for academic intervention.', '/guidance/inbox', 0, '2026-06-04 10:15:00'),
(3, 5, 'Case Updated', 'Incident #19 (Peer Conflict) has been updated. New response added.', '/guidance/incidents/19', 0, '2026-06-04 15:00:00'),
(4, 5, 'Urgent Referral', 'URGENT: Student marked with possible self-harm markings. Immediate attention required.', '/guidance/inbox', 0, '2026-06-05 09:15:00'),
(5, 5, 'New Referral Received', 'Student with chronic tardiness referred for guidance intervention.', '/guidance/inbox', 0, '2026-06-05 11:15:00'),
(6, 5, 'Appointment Reminder', 'You have a parent meeting scheduled today at 2:00 PM for incident #23.', '/guidance/incidents/23', 1, '2026-06-05 08:00:00');

-- --------------------------------------------------------
-- Update auto_increment values
-- --------------------------------------------------------
ALTER TABLE `students` AUTO_INCREMENT = 25;
ALTER TABLE `incident_reports` AUTO_INCREMENT = 26;
ALTER TABLE `referrals` AUTO_INCREMENT = 12;
ALTER TABLE `responses` AUTO_INCREMENT = 9;
ALTER TABLE `notifications` AUTO_INCREMENT = 7;
ALTER TABLE `sections` AUTO_INCREMENT = 9;
ALTER TABLE `incident_types` AUTO_INCREMENT = 13;
