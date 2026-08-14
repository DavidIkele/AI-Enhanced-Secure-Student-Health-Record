-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: student_health_qa
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `ai_predictions`
--

/*!40000 ALTER TABLE `ai_predictions` DISABLE KEYS */;
INSERT INTO `ai_predictions` (`id`, `student_id`, `prediction_type`, `risk_level`, `risk_score`, `confidence`, `model_version`, `features_snapshot`, `explanation`, `status`, `requested_by`, `created_at`) VALUES (1,1,'malaria_risk','low',0.1800,0.9200,'lr-v1.0','{\"recent_visits_30d\":2,\"fever_history\":1,\"season\":\"rainy\"}','Low predicted risk based on recent visit patterns. Informational only; not a diagnosis.','delivered',2,'2026-08-12 13:26:06'),(2,2,'asthma_exacerbation','moderate',0.5600,0.8400,'rf-v1.0','{\"history_asthma\":1,\"recent_visits_30d\":1,\"exercise_related\":1}','Moderate predicted risk of asthma-related visit in the next 4 weeks. Informational only.','delivered',3,'2026-08-12 13:26:06');
/*!40000 ALTER TABLE `ai_predictions` ENABLE KEYS */;

--
-- Dumping data for table `appointments`
--

/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` (`id`, `student_id`, `healthcare_staff_id`, `scheduled_at`, `duration_minutes`, `reason`, `status`, `cancellation_reason`, `admin_notes`, `requested_by`, `handled_by`, `created_at`, `updated_at`) VALUES (1,1,2,'2026-08-12 10:00:00',30,'Follow-up review','approved',NULL,NULL,4,3,'2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,2,2,'2026-08-14 11:00:00',30,'Asthma review','pending',NULL,NULL,5,NULL,'2026-08-12 13:26:06','2026-08-12 13:26:06'),(3,1,1,'2026-08-05 09:30:00',30,'Lab result collection','completed',NULL,NULL,4,2,'2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;

--
-- Dumping data for table `audit_logs`
--

/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `request_method`, `request_path`, `created_at`) VALUES (1,NULL,'database.seeded','system','seed-v1',NULL,'{\"roles\":3,\"permissions\":27,\"users\":5}','127.0.0.1','database/seed.php','CLI','database/seed.php','2026-08-12 13:26:06'),(2,1,'user.created','user','1',NULL,'{\"username\":\"admin\",\"role\":\"admin\"}','127.0.0.1','database/seed.php','CLI','database/seed.php','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;

--
-- Dumping data for table `clinic_visits`
--

/*!40000 ALTER TABLE `clinic_visits` DISABLE KEYS */;
INSERT INTO `clinic_visits` (`id`, `student_id`, `healthcare_staff_id`, `visited_at`, `visit_type`, `reason`, `chief_complaint`, `assessment_notes`, `outcome`, `status`, `created_by`, `created_at`, `updated_at`) VALUES (1,1,2,'2026-07-02 10:30:00','routine','Annual medical check-up','No acute complaint; routine review.','Fit and healthy. Recommend continuing regular exercise.','discharged','closed',3,'2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,1,1,'2026-07-20 09:15:00','follow_up','Malaria test results review','Fever, headache and body ache for two days.','Positive RDT for malaria. Prescribed antimalarial course.','treated','closed',2,'2026-08-12 13:26:06','2026-08-12 13:26:06'),(3,2,2,'2026-07-25 14:00:00','initial','Asthmatic episode','Difficulty breathing and wheezing after physical exercise.','Mild bronchospasm. Nebulisation given; response good.','treated','closed',3,'2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `clinic_visits` ENABLE KEYS */;

--
-- Dumping data for table `diagnoses`
--

/*!40000 ALTER TABLE `diagnoses` DISABLE KEYS */;
INSERT INTO `diagnoses` (`id`, `clinic_visit_id`, `icd_code`, `name`, `description`, `severity`, `is_primary`, `diagnosed_by`, `diagnosed_at`, `created_at`, `updated_at`) VALUES (1,1,NULL,'Healthy - routine examination','No active disease identified.','mild',1,3,'2026-07-02','2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,2,'B54','Malaria (unspecified)','Confirmed via rapid diagnostic test.','moderate',1,2,'2026-07-20','2026-08-12 13:26:06','2026-08-12 13:26:06'),(3,3,'J45.9','Asthma exacerbation','Mild bronchospasm post-exercise.','moderate',1,3,'2026-07-25','2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `diagnoses` ENABLE KEYS */;

--
-- Dumping data for table `health_alerts`
--

/*!40000 ALTER TABLE `health_alerts` DISABLE KEYS */;
INSERT INTO `health_alerts` (`id`, `student_id`, `alert_type`, `severity`, `title`, `message`, `metadata`, `is_resolved`, `resolved_by`, `resolved_at`, `created_at`) VALUES (1,2,'personal','warning','Asthma follow-up recommended','Based on recent visits, a follow-up asthma review is recommended within 4 weeks.','{\"insight\":\"asthma_exacerbation\",\"model_version\":\"rf-v1.0\"}',0,NULL,NULL,'2026-08-12 13:26:06');
/*!40000 ALTER TABLE `health_alerts` ENABLE KEYS */;

--
-- Dumping data for table `health_insights`
--

/*!40000 ALTER TABLE `health_insights` DISABLE KEYS */;
INSERT INTO `health_insights` (`id`, `student_id`, `insight_type`, `title`, `content`, `data_version`, `status`, `is_read`, `read_at`, `created_at`) VALUES (1,1,'visit_pattern','Your recent clinic visits','You visited the clinic twice in the last 30 days. Keep up with your preventive check-ups.','analytics-v1.0','active',0,NULL,'2026-08-12 13:26:06'),(2,2,'preventive','Asthma management tip','Avoiding exercise triggers and carrying your inhaler can reduce asthma flare-ups. Please discuss with clinic staff for personalised advice.','insight-v1.0','active',0,NULL,'2026-08-12 13:26:06');
/*!40000 ALTER TABLE `health_insights` ENABLE KEYS */;

--
-- Dumping data for table `health_records`
--

/*!40000 ALTER TABLE `health_records` DISABLE KEYS */;
INSERT INTO `health_records` (`id`, `student_id`, `blood_group`, `genotype`, `height_cm`, `weight_kg`, `allergies`, `chronic_conditions`, `disabilities`, `family_history`, `notes`, `created_by`, `updated_by`, `created_at`, `updated_at`) VALUES (1,1,'O+','AA',175.00,68.50,'Penicillin','None','None','Hypertension (father)','No significant findings.',2,2,'2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,2,'B+','AS',162.00,55.00,'None','Asthma','None','Diabetes (mother)','',3,3,'2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `health_records` ENABLE KEYS */;

--
-- Dumping data for table `healthcare_staff`
--

/*!40000 ALTER TABLE `healthcare_staff` DISABLE KEYS */;
INSERT INTO `healthcare_staff` (`id`, `user_id`, `staff_id`, `title`, `first_name`, `last_name`, `other_names`, `role_name`, `specialization`, `department`, `phone`, `email`, `is_active`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,2,'UNZIK-HS-001','Nurse','Adaeze','Eze',NULL,'Registered Nurse','General Practice','Student Health Centre','+2348055551111','nurse@unizik.edu.ng',1,'2026-08-12 13:26:06','2026-08-12 13:26:06',NULL),(2,3,'UNZIK-HS-002','Dr.','Ikenna','Obi','M.','Medical Officer','General Medicine','Student Health Centre','+2348055552222','doctor@unizik.edu.ng',1,'2026-08-12 13:26:06','2026-08-12 13:26:06',NULL);
/*!40000 ALTER TABLE `healthcare_staff` ENABLE KEYS */;

--
-- Dumping data for table `login_attempts`
--

/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;

--
-- Dumping data for table `medical_histories`
--

/*!40000 ALTER TABLE `medical_histories` DISABLE KEYS */;
INSERT INTO `medical_histories` (`id`, `student_id`, `condition_name`, `description`, `onset_date`, `is_recurring`, `severity`, `status`, `recorded_by`, `created_at`, `updated_at`) VALUES (1,2,'Asthma','Intermittent wheezing since childhood, triggered by cold air.','2015-01-10',1,'moderate','active',3,'2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,1,'Migraine','Occasional migraines with aura.','2021-03-01',1,'mild','active',3,'2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `medical_histories` ENABLE KEYS */;

--
-- Dumping data for table `medications`
--

/*!40000 ALTER TABLE `medications` DISABLE KEYS */;
INSERT INTO `medications` (`id`, `treatment_id`, `clinic_visit_id`, `name`, `dosage`, `frequency`, `route`, `quantity`, `duration_days`, `instructions`, `status`, `prescribed_by`, `prescribed_at`, `created_at`, `updated_at`) VALUES (1,1,2,'Artemether 20mg / Lumefantrine 120mg','4 tablets','Twice daily for 3 days','Oral','24 tablets',3,'Take with food.','active',2,'2026-07-20','2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,2,3,'Salbutamol inhaler 100mcg','1 puff','As needed (max 4/hour)','Inhalation','1 inhaler',14,'Use with spacer. Carry at all times.','active',3,'2026-07-25','2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `medications` ENABLE KEYS */;

--
-- Dumping data for table `notifications`
--

/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `reference_type`, `reference_id`, `is_read`, `read_at`, `created_at`) VALUES (1,4,'appointment','Appointment approved','Your appointment on 12 Aug 2026 at 10:00 has been approved.','appointment',1,0,NULL,'2026-08-12 13:26:06'),(2,5,'alert','Health advisory','A new health advisory is available for your review.','health_alert',1,0,NULL,'2026-08-12 13:26:06');
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;

--
-- Dumping data for table `outbreak_analytics`
--

/*!40000 ALTER TABLE `outbreak_analytics` DISABLE KEYS */;
INSERT INTO `outbreak_analytics` (`id`, `illness_category`, `period_start`, `period_end`, `baseline_count`, `observed_count`, `z_score`, `alert_level`, `is_flagged`, `created_by`, `created_at`) VALUES (1,'Malaria','2026-07-01','2026-07-31',8,9,1.200,'none',0,2,'2026-08-12 13:26:06'),(2,'Malaria','2026-06-01','2026-06-30',8,7,0.100,'none',0,2,'2026-08-12 13:26:06');
/*!40000 ALTER TABLE `outbreak_analytics` ENABLE KEYS */;

--
-- Dumping data for table `permissions`
--

/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` (`id`, `slug`, `name`, `description`, `created_at`, `updated_at`) VALUES (1,'auth.login','Log in','Sign in to the system','2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,'auth.logout','Log out','Sign out of the system','2026-08-12 13:26:06','2026-08-12 13:26:06'),(3,'profile.view.own','View own student profile','View the authenticated user profile','2026-08-12 13:26:06','2026-08-12 13:26:06'),(4,'records.view.own','View own health records','View personal health record data','2026-08-12 13:26:06','2026-08-12 13:26:06'),(5,'records.manage','Create/update health records','Create and update student health records','2026-08-12 13:26:06','2026-08-12 13:26:06'),(6,'records.view.any','View any student health record','View health records of any enrolled student','2026-08-12 13:26:06','2026-08-12 13:26:06'),(7,'records.delete','Delete permitted records','Delete records the user is permitted to delete','2026-08-12 13:26:06','2026-08-12 13:26:06'),(8,'visits.manage','Manage clinic visits','Create and update clinic visit encounters','2026-08-12 13:26:06','2026-08-12 13:26:06'),(9,'diagnoses.manage','Manage diagnoses','Create and update diagnoses for clinic visits','2026-08-12 13:26:06','2026-08-12 13:26:06'),(10,'treatments.manage','Manage treatments','Create and update treatment plans','2026-08-12 13:26:06','2026-08-12 13:26:06'),(11,'medications.manage','Manage medications','Create and update prescribed medications','2026-08-12 13:26:06','2026-08-12 13:26:06'),(12,'vitals.manage','Manage vital signs','Create and update vital sign measurements','2026-08-12 13:26:06','2026-08-12 13:26:06'),(13,'appointments.request','Request an appointment','Request a clinic appointment','2026-08-12 13:26:06','2026-08-12 13:26:06'),(14,'appointments.manage','Manage appointments','Manage the appointment booking workflow','2026-08-12 13:26:06','2026-08-12 13:26:06'),(15,'appointments.approve','Approve/reject appointments','Approve or reject appointment requests','2026-08-12 13:26:06','2026-08-12 13:26:06'),(16,'analytics.view','View aggregate analytics','View aggregate visit and outbreak analytics','2026-08-12 13:26:06','2026-08-12 13:26:06'),(17,'analytics.manage','Run/manage analytics jobs','Run and manage analytics detection jobs','2026-08-12 13:26:06','2026-08-12 13:26:06'),(18,'ai.request','Request AI decision support','Request AI-powered risk predictions','2026-08-12 13:26:06','2026-08-12 13:26:06'),(19,'ai.manage','Manage AI model/service','Administer the AI decision-support service','2026-08-12 13:26:06','2026-08-12 13:26:06'),(20,'insights.view','View personalized health insights','View personalized non-diagnostic health insights','2026-08-12 13:26:06','2026-08-12 13:26:06'),(21,'alerts.manage','Manage health alerts','Send and manage student health alerts','2026-08-12 13:26:06','2026-08-12 13:26:06'),(22,'outbreak.manage','Manage outbreak/pattern alerts','Manage outbreak detection results and alerts','2026-08-12 13:26:06','2026-08-12 13:26:06'),(23,'users.manage','Manage users','Create, update and deactivate user accounts','2026-08-12 13:26:06','2026-08-12 13:26:06'),(24,'roles.manage','Manage roles/permissions','Administer roles and permission grants','2026-08-12 13:26:06','2026-08-12 13:26:06'),(25,'audit.view','View audit logs','View the append-only audit trail','2026-08-12 13:26:06','2026-08-12 13:26:06'),(26,'notifications.manage','Manage notifications','Manage in-app notifications','2026-08-12 13:26:06','2026-08-12 13:26:06'),(27,'system.health','View system health','View system health and diagnostics','2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;

--
-- Dumping data for table `role_permission`
--

/*!40000 ALTER TABLE `role_permission` DISABLE KEYS */;
INSERT INTO `role_permission` (`role_id`, `permission_id`) VALUES (1,1),(1,2),(1,3),(1,4),(1,5),(1,6),(1,7),(1,8),(1,9),(1,10),(1,11),(1,12),(1,13),(1,14),(1,15),(1,16),(1,17),(1,18),(1,19),(1,20),(1,21),(1,22),(1,23),(1,24),(1,25),(1,26),(1,27),(2,1),(2,2),(2,3),(2,4),(2,5),(2,6),(2,8),(2,9),(2,10),(2,11),(2,12),(2,13),(2,14),(2,15),(2,16),(2,18),(2,20),(2,21),(2,26),(3,1),(3,2),(3,3),(3,4),(3,13),(3,20),(3,26);
/*!40000 ALTER TABLE `role_permission` ENABLE KEYS */;

--
-- Dumping data for table `roles`
--

/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` (`id`, `slug`, `name`, `description`, `created_at`, `updated_at`) VALUES (1,'admin','Administrator','Full system administrator. Access to all modules and administrative functions.','2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,'staff','Healthcare Staff','Clinic staff who create, view and manage student health records.','2026-08-12 13:26:06','2026-08-12 13:26:06'),(3,'student','Student','Students who view their own health data and request appointments.','2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;

--
-- Dumping data for table `students`
--

/*!40000 ALTER TABLE `students` DISABLE KEYS */;
INSERT INTO `students` (`id`, `user_id`, `reg_number`, `first_name`, `last_name`, `other_names`, `date_of_birth`, `gender`, `email`, `phone`, `address`, `department`, `faculty`, `level_of_study`, `emergency_contact_name`, `emergency_contact_phone`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,4,'2020001234','Chinedu','Okeke','Samuel','2002-05-14','male','student.ade@unizik.edu.ng','+2348012345678','Behind New Lecture Theatre, Awka','Computer Science','Faculty of Physical Sciences','400','Mrs. Ngozi Okeke','+2348034567890','2026-08-12 13:26:06','2026-08-12 13:26:06',NULL),(2,5,'2021005678','Amina','Bala',NULL,'2003-02-27','female','student.bala@unizik.edu.ng','+2348098765432','Anambra State Housing Estate, Awka','Nursing Science','Faculty of Health Sciences','300','Mr. Ibrahim Bala','+2348067890123','2026-08-12 13:26:06','2026-08-12 13:26:06',NULL);
/*!40000 ALTER TABLE `students` ENABLE KEYS */;

--
-- Dumping data for table `treatments`
--

/*!40000 ALTER TABLE `treatments` DISABLE KEYS */;
INSERT INTO `treatments` (`id`, `clinic_visit_id`, `diagnosis_id`, `name`, `description`, `treatment_type`, `started_at`, `ended_at`, `status`, `prescribed_by`, `created_at`, `updated_at`) VALUES (1,2,2,'Antimalarial therapy','Artemether-Lumefantrine course.','medication','2026-07-20',NULL,'completed',2,'2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,3,3,'Nebulisation','Salbutamol nebulisation for bronchospasm.','therapy','2026-07-25',NULL,'completed',3,'2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `treatments` ENABLE KEYS */;

--
-- Dumping data for table `user_permission`
--

/*!40000 ALTER TABLE `user_permission` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_permission` ENABLE KEYS */;

--
-- Dumping data for table `user_preferences`
--

/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;

--
-- Dumping data for table `users`
--

/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role_id`, `is_active`, `must_change_password`, `last_login_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`, `deleted_at`) VALUES (1,'admin','admin@unizik.edu.ng','$argon2id$v=19$m=65536,t=4,p=2$VUgxLi8uc3BpdmVZZFZHbg$UxK7o+/6UlE//RXCoBCpdgQRgyFv3enrLk0ov4lrK50',1,1,0,NULL,0,NULL,'2026-08-12 13:26:06','2026-08-12 13:26:06',NULL),(2,'nurse','nurse@unizik.edu.ng','$argon2id$v=19$m=65536,t=4,p=2$cTFFTmhMUkdrTFBJc014ag$opsJvvm8wHH4tod3KXDHs3r+ryU+MWTEn6DXCPD6zK4',2,1,0,NULL,0,NULL,'2026-08-12 13:26:06','2026-08-12 13:26:06',NULL),(3,'doctor','doctor@unizik.edu.ng','$argon2id$v=19$m=65536,t=4,p=2$ZmMzaFJZSTIxN3ZPalBzNA$QCO+rdHA+aX1AXGW3x08J79KV5K95UYME+d0n24mgNg',2,1,0,NULL,0,NULL,'2026-08-12 13:26:06','2026-08-12 13:26:06',NULL),(4,'ade','student.ade@unizik.edu.ng','$argon2id$v=19$m=65536,t=4,p=2$amJZdTIxcE9kVlhDcGwxeQ$I9K7Xsp9kJvRMoMMX3nsa+0+c1iPkBwng986kQNZy/o',3,1,0,NULL,0,NULL,'2026-08-12 13:26:06','2026-08-12 13:26:06',NULL),(5,'bala','student.bala@unizik.edu.ng','$argon2id$v=19$m=65536,t=4,p=2$QUMud1drdHFoS3BtN0t6eQ$V2IUzf/HLDmMBgH7YymPRdmZoZu7FVng8Bw67sQehxQ',3,1,0,NULL,0,NULL,'2026-08-12 13:26:06','2026-08-12 13:26:06',NULL);
/*!40000 ALTER TABLE `users` ENABLE KEYS */;

--
-- Dumping data for table `vital_signs`
--

/*!40000 ALTER TABLE `vital_signs` DISABLE KEYS */;
INSERT INTO `vital_signs` (`id`, `clinic_visit_id`, `student_id`, `temperature_c`, `blood_pressure_sys`, `blood_pressure_dia`, `heart_rate`, `respiratory_rate`, `oxygen_saturation`, `weight_kg`, `height_cm`, `bmi`, `measured_at`, `recorded_by`, `created_at`, `updated_at`) VALUES (1,2,1,38.6,118,78,96,18,98,68.50,175.00,22.4,'2026-07-20 09:20:00',2,'2026-08-12 13:26:06','2026-08-12 13:26:06'),(2,3,2,37.1,124,80,104,24,93,55.00,162.00,21.0,'2026-07-25 14:05:00',3,'2026-08-12 13:26:06','2026-08-12 13:26:06');
/*!40000 ALTER TABLE `vital_signs` ENABLE KEYS */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-13  4:36:08
