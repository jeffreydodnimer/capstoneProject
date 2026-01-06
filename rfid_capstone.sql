-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 06, 2026 at 07:54 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rfid_capstone`
--

-- --------------------------------------------------------

--
-- Table structure for table `advisers`
--

CREATE TABLE `advisers` (
  `employee_id` int(20) NOT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `pass` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Triggers `advisers`
--
DELIMITER $$
CREATE TRIGGER `after_adviser_insert` AFTER INSERT ON `advisers` FOR EACH ROW BEGIN
    INSERT INTO faculty_login (employee_id, status) VALUES (NEW.employee_id, 'active');
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `lrn` bigint(50) DEFAULT NULL,
  `enrollment_id` int(11) DEFAULT NULL,
  `date` date DEFAULT NULL,
  `time_in` datetime NOT NULL,
  `time_out` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT 'present'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enrollment_id` int(11) NOT NULL,
  `lrn` bigint(50) DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `school_year` varchar(9) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_login`
--

CREATE TABLE `faculty_login` (
  `faculty_id` int(11) NOT NULL,
  `employee_id` int(20) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guardians`
--

CREATE TABLE `guardians` (
  `guardian_id` int(11) NOT NULL,
  `lrn` bigint(50) NOT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `contact_number` bigint(50) DEFAULT NULL,
  `relationship_to_student` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rfid`
--

CREATE TABLE `rfid` (
  `rfid_id` int(11) NOT NULL,
  `rfid_number` bigint(20) DEFAULT NULL,
  `lrn` bigint(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(50) DEFAULT NULL,
  `grade_level` varchar(10) DEFAULT NULL,
  `employee_id` int(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `lrn` bigint(50) NOT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `middlename` varchar(50) DEFAULT NULL,
  `suffix` varchar(50) DEFAULT NULL,
  `age` int(50) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `sex` enum('Male','Female') DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`lrn`, `lastname`, `firstname`, `middlename`, `suffix`, `age`, `birthdate`, `sex`, `profile_image`) VALUES
(102066140216, 'YLARDE', 'MARK ANGELO', 'PAYA', '', 17, '2009-01-22', 'Male', NULL),
(102829130068, 'ALTEZ', 'JHAN CARLO', 'UBANA', '', 18, '2007-11-07', 'Male', NULL),
(102829150004, 'ALTEZ', 'JOHN ANGELO', 'UBA?A', '', 16, '2010-01-01', 'Male', NULL),
(107156140151, 'BALANE', 'JAMES LIAN', 'MABUTING', '', 16, '2009-07-23', 'Male', NULL),
(107552170002, 'ANCAJA', 'BRENT GWAYNE', 'LUMANGLAS', '', 14, '2011-12-10', 'Male', NULL),
(107968160165, 'QUINDOZA', 'ANDREI', 'PACHECO', '', 14, '2011-10-29', 'Male', NULL),
(107969180121, 'QUINDOZA', 'AYESSA', '', '', 12, '2013-09-15', 'Female', NULL),
(108134120064, 'PASAMIC', 'ADRIAN WESLY', 'PULIDO', '', 19, '2006-12-24', 'Male', NULL),
(108225140778, 'PACAYRA', 'EDILITHA', 'DISONGLO', '', 18, '2007-11-22', 'Female', NULL),
(108228150043, 'LOPEZ', 'CYLINE NICOLE', 'PACAYRA', '', 15, '2010-08-24', 'Female', NULL),
(108497140032, 'ABAYON', 'JHIAN CARLO', 'TOLENTINO', '', 17, '2008-11-06', 'Male', NULL),
(108509170216, 'ORTIZ', 'ZYLEN JEAN', 'VILLAVERDE', '', 14, '2011-12-16', 'Female', NULL),
(108511170009, 'GULLA', 'MCHARRY', 'SERNA', '', 14, '2011-12-14', 'Male', NULL),
(108519170021, 'OVAR', 'RHENZ', '', '', 15, '2011-02-18', 'Male', NULL),
(108525140001, 'CAMARA', 'RENIEL', 'VILLACRUEL', '', 18, '2008-01-11', 'Male', NULL),
(108525150004, 'LOPEZ', 'GIAN CLARK', 'RODIL', '', 16, '2010-08-01', 'Male', NULL),
(108530150006, 'EROLES', 'MARIA ANN', 'LARCENA', '', 16, '2010-07-07', 'Female', NULL),
(108638130117, 'OCTOMAN', 'JOHN LLOYD', 'LEOP', '', 18, '2008-07-09', 'Male', NULL),
(108653140014, 'RAMOS', 'MARY CLAIRE', 'ACLAN', '', 17, '2008-12-05', 'Female', NULL),
(108653160007, 'RAMOS', 'QUENIE MAE', 'ACLAN', '', 15, '2011-05-28', 'Female', NULL),
(108840170068, 'BALANE', 'ALYANA', 'MABUTING', '', 14, '2012-03-30', 'Female', NULL),
(108944140071, 'BUENDIA', 'KEN IVAN CODY', 'PORTES', '', 16, '2009-09-16', 'Male', NULL),
(108944160011, 'LAGRASON', 'PRINCESS', 'CALBO', '', 15, '2010-11-03', 'Female', NULL),
(108946140022, 'MANALO', 'KETHLYN', 'RODRIGUEZ', '', 16, '2009-07-26', 'Female', NULL),
(108946160004, 'ANDA', 'CLESHALL ANNE', 'OCAN', '', 15, '2010-12-27', 'Female', NULL),
(108946160005, 'CASTILLO', 'GERALDINE', 'CASTILLO', '', 15, '2010-11-12', 'Female', NULL),
(108946160018, 'YTANG', 'LYNJOY', 'URSAL', '', 15, '2010-09-16', 'Female', NULL),
(108947130007, 'EROLES', 'NEIL WINCHESTER', 'MAAT', '', 18, '2007-12-28', 'Male', NULL),
(108949170036, 'FLORES', 'LANCE ANGEL', 'DE TORRES', '', 14, '2012-07-14', 'Male', NULL),
(108950140010, 'DELIN', 'ROMA JANE', 'BAUTISTA', '', 16, '2009-10-19', 'Female', NULL),
(108950160010, 'FAJARDO', 'DAXEN', 'NERA', '', 15, '2011-01-18', 'Male', NULL),
(108959130021, 'DELIN', 'MARIANE', 'SEGUI', '', 18, '2008-04-26', 'Female', NULL),
(108959130023, 'ODNIMER', 'DONALYN', 'ABAYON', '', 18, '2008-03-02', 'Female', NULL),
(108959140002, 'CONSTANTINO', 'ARLLYN', 'CURATCHO', '', 17, '2008-10-25', 'Female', NULL),
(108959140004, 'GLORIOSO', 'ALYSSA', 'DELIN', '', 17, '2008-10-15', 'Female', NULL),
(108959170001, 'CONSTANTINO', 'ANTHONY', 'CURATCHO', '', 15, '2011-07-12', 'Male', NULL),
(108959170002, 'GLORIOSO', 'ANDREY', 'DELIN', '', 14, '2012-03-16', 'Male', NULL),
(108959170003, 'SANCHEZ', 'ANGEL', 'CAMBARIHAN', '', 14, '2012-05-28', 'Female', NULL),
(108959180001, 'ALOTA', 'JUNEL', 'BAUTISTA', '', 13, '2013-01-01', 'Male', NULL),
(108960130004, 'DE GUZMAN', 'JUN JUN', '', '', 18, '2008-06-17', 'Male', NULL),
(108960130007, 'DEL MUNDO', 'ADRIAN', 'REJUSO', '', 17, '2008-08-05', 'Male', NULL),
(108960130008, 'LASHERAS', 'JON DARREN', 'ANDALAJAO', '', 18, '2008-03-07', 'Male', NULL),
(108960130010, 'CAMBA', 'KC', 'BANA', '', 17, '2008-08-20', 'Female', NULL),
(108960130012, 'DIONELA', 'JUDITH', 'DE GUZMAN', '', 18, '2008-01-13', 'Female', NULL),
(108960130017, 'ALEGRE', 'ASHLIE MAE', 'MONTANO', '', 17, '2008-10-10', 'Female', NULL),
(108960140001, 'GONZALES', 'PRINCESS KRISHA', 'SAN JOSE', '', 17, '2008-09-02', 'Female', NULL),
(108960140003, 'DE LARA', 'CJ', 'PERALTA', '', 17, '2009-02-01', 'Male', NULL),
(108960140006, 'DEL MUNDO', 'ALLAN', 'DAPULA', 'JR', 17, '2009-04-06', 'Male', NULL),
(108960140007, 'DOMINGO', 'AJ', 'QUINDOZA', '', 16, '2009-09-06', 'Male', NULL),
(108960140009, 'MARBID', 'LANCE', 'YOPYOP', '', 17, '2009-04-30', 'Male', NULL),
(108960140010, 'MAGCAMIT', 'RHEN JAY', 'YOPYOP', '', 17, '2009-01-16', 'Male', NULL),
(108960140011, 'MAGNAYE', 'ALJHUN JAMES', 'ILLUSTRE', '', 16, '2009-08-24', 'Male', NULL),
(108960140012, 'OFLARIA', 'VON ALEX', 'DE LOS SANTOS', '', 17, '2009-05-25', 'Male', NULL),
(108960140014, 'ARATEA', 'JENNYLYN', 'PERALTA', '', 17, '2009-01-13', 'Female', NULL),
(108960140016, 'DE GUZMAN', 'ARLENE', 'BALAORO', '', 17, '2009-02-28', 'Female', NULL),
(108960140025, 'PARONE', 'MARIAN JOY', 'MELLANES', '', 17, '2009-03-03', 'Female', NULL),
(108960140026, 'RIEGO', 'RHIAN', 'DE GUZMAN', '', 17, '2009-06-18', 'Female', NULL),
(108960140027, 'YOPYOP', 'WELLA ERICKA', 'ANDAL', '', 17, '2008-12-13', 'Female', NULL),
(108960140029, 'ANGELES', 'ANA JEAN', 'DE GUZMAN', '', 17, '2009-06-11', 'Female', NULL),
(108960150001, 'DE RAMA', 'JULIUS', 'CORONADO', '', 16, '2009-10-18', 'Male', NULL),
(108960150002, 'YOPYOP', 'JACK', 'TULLE', '', 16, '2010-03-23', 'Male', NULL),
(108960150008, 'TIOSAN', 'RENELYN', 'CARANDANG', '', 16, '2010-03-09', 'Female', NULL),
(108960150009, 'BANDIOLA', 'ALEXAH', 'DOMINGO', '', 16, '2010-07-20', 'Female', NULL),
(108960150010, 'LUCES', 'JELLY', 'OFLARIA', '', 15, '2010-08-29', 'Female', NULL),
(108960150012, 'CATAAG', 'JAZMINE', 'MANGUE', '', 16, '2009-10-22', 'Female', NULL),
(108960150013, 'LASCIERAS', 'PATRICK', 'FIGUEROA', '', 16, '2010-08-21', 'Male', NULL),
(108960160001, 'AYAPANA', 'JAKE', 'PUREZA', '', 15, '2011-05-16', 'Male', NULL),
(108960160007, 'MACALINDOL', 'ALJON', 'DOMINGO', '', 15, '2011-05-09', 'Male', NULL),
(108960160009, 'PISIG', 'MATT LAWRENCE', 'BASCO', '', 15, '2010-11-01', 'Male', NULL),
(108960160011, 'ALEGRE', 'LORELYN MAE', 'MONTANO', '', 15, '2011-05-25', 'Female', NULL),
(108960160014, 'GONZALES', 'AMARAH', 'SAN JOSE', '', 15, '2011-04-03', 'Female', NULL),
(108960160015, 'DE LEON', 'RENSO', 'OLLERES', '', 15, '2011-05-13', 'Male', NULL),
(108960160016, 'DEL MUNDO', 'ANA JOY', 'DAPULA', '', 15, '2011-06-10', 'Female', NULL),
(108960160017, 'VILLASOTO', 'MICHELLE', 'PERALTA', '', 15, '2011-03-23', 'Female', NULL),
(108960170002, 'DEL MUNDO', 'JOHN VINCE', 'REJUSO', '', 14, '2012-03-09', 'Male', NULL),
(108960170003, 'MAPILOT', 'RHONEL', 'DOMINGO', '', 14, '2012-04-20', 'Male', NULL),
(108960170005, 'YOPYOP', 'JOHN PAUL', 'ANDAL', '', 14, '2012-01-01', 'Male', NULL),
(108960170006, 'TIOSAN', 'MIKE ANDRIE', 'CARANDANG', '', 14, '2012-01-07', 'Male', NULL),
(108960170007, 'CATAAG', 'JESSA MAE', 'MANGUE', '', 14, '2012-01-05', 'Female', NULL),
(108960170008, 'LAGAR', 'AYESHA', 'PARANGALAN', '', 14, '2012-04-24', 'Female', NULL),
(108960170009, 'LLANES', 'BRIANNA VIEN', 'VERAN', '', 14, '2011-09-24', 'Female', NULL),
(108960170010, 'PERALTA', 'CHRIS LORENZ', 'BULAWAN', '', 14, '2011-12-16', 'Male', NULL),
(108960170011, 'LAGAR', 'ANDREA', 'PARANGALAN', '', 14, '2012-04-24', 'Female', NULL),
(108960180001, 'AYAPANA', 'KENT BENEDICK', 'PUREZA', '', 13, '2013-07-14', 'Male', NULL),
(108960180002, 'DIONELA', 'CEEJAY', 'DE GUZMAN', '', 13, '2012-09-16', 'Male', NULL),
(108960180003, 'DOMINGO', 'NIGEL', 'BAROTILLO', '', 13, '2012-12-05', 'Male', NULL),
(108960180005, 'LANDICHO', 'KEN ANDREW', 'DELA PE?A', '', 12, '2013-08-30', 'Male', NULL),
(108960180007, 'YOPYOP', 'ALDRED', 'TULLE', '', 13, '2012-10-27', 'Male', NULL),
(108960180008, 'LANDICHO', 'JOHN PHILIP', 'BALEDIO', '', 13, '2012-09-06', 'Male', NULL),
(108960180011, 'DELA PE?A', 'LIANA ERIKA', 'ESCAMILLAS', '', 12, '2013-08-28', 'Female', NULL),
(108960180012, 'LACE', 'REYSA', 'VISMONTE', '', 14, '2012-07-26', 'Female', NULL),
(108960180013, 'LASCIERAS', 'KATE', 'FIGUEROA', '', 12, '2014-03-21', 'Female', NULL),
(108960180014, 'LASCIERAS', 'KAYE', 'FIGUEROA', '', 12, '2013-10-13', 'Female', NULL),
(108960180015, 'MACALINDOL', 'ALTHEEA', 'DOMINGO', '', 13, '2013-05-20', 'Female', NULL),
(108960180016, 'MARASIGAN', 'EUNICE', 'BULIGAO', '', 13, '2012-09-19', 'Female', NULL),
(108960180019, 'BALLESTEROS', 'JENNYLYN', 'DE GUZMAN', '', 13, '2013-01-07', 'Female', NULL),
(108960180020, 'PINCA', 'PAOLO', 'DEL MUNDO', '', 13, '2013-02-14', 'Male', NULL),
(108960180022, 'GONZALES', 'AYESSA', 'SAN JOSE', '', 13, '2013-06-03', 'Female', NULL),
(108960180025, 'REJOSO', 'RICAH', 'DELA CRUZ', '', 14, '2012-07-19', 'Female', NULL),
(108961090038, 'ESTRADA', 'WALTER', 'CAMBARIHAN', '', 25, '2001-05-14', 'Male', NULL),
(108961120009, 'YTANG', 'ELJHON', 'URSAL', '', 21, '2005-04-03', 'Male', NULL),
(108961120143, 'BALIWAG', 'RONALYN', 'MENDOZA', '', 18, '2007-08-10', 'Female', NULL),
(108961130003, 'ANGELES', 'AUDRAIN TAHJ', 'BANDIANO', '', 18, '2008-01-10', 'Male', NULL),
(108961130007, 'DEGRAS', 'LANCE', 'PIA', '', 18, '2007-11-19', 'Male', NULL),
(108961130009, 'DIPASUPIL', 'ANTONIO', 'AMANDY', 'JR', 18, '2008-05-14', 'Male', NULL),
(108961130011, 'EROLES', 'AERON', 'MANALO', '', 18, '2008-05-03', 'Male', NULL),
(108961130012, 'EROLES', 'JVEE', 'CASTRO', '', 18, '2007-12-28', 'Male', NULL),
(108961130013, 'FAJARDO', 'MARK LEO', 'VENDER', '', 18, '2007-10-18', 'Male', NULL),
(108961130014, 'HITOSIS', 'JOHN REYMOND', 'DELA PE?A', '', 18, '2008-04-22', 'Male', NULL),
(108961130015, 'MATRE', 'IZAN ROI', 'DIVINAFLOR', '', 18, '2008-04-12', 'Male', NULL),
(108961130016, 'NIEVA', 'JAN ISAAC', 'FABELLON', '', 18, '2007-11-09', 'Male', NULL),
(108961130018, 'VALDERAMA', 'MICCO', 'BUEDRON', '', 17, '2008-08-17', 'Male', NULL),
(108961130019, 'YOPYOP', 'SHERLO', 'ROGELIO', '', 18, '2008-02-16', 'Male', NULL),
(108961130020, 'AN', 'TRIZIA DINES', 'SAN JUAN', '', 18, '2008-01-04', 'Female', NULL),
(108961130021, 'BONSOL', 'DENBELL', 'AGATON', '', 17, '2008-01-31', 'Female', NULL),
(108961130022, 'CAY', 'KIENNELLA CHYRELLE', 'AGUIBA', '', 18, '2007-11-09', 'Female', NULL),
(108961130023, 'CENA', 'KEITH FRANCHESCA', 'RAMOS', '', 18, '2008-04-21', 'Female', NULL),
(108961130024, 'DE CASTRO', 'JAMAICA JOY', 'TEOXON', '', 18, '2008-06-04', 'Female', NULL),
(108961130026, 'MONDOY', 'MARIAN', 'DELA PE?A', '', 17, '2008-08-04', 'Female', NULL),
(108961130030, 'VERGANIO', 'FREYA ANN PATRISH', 'EROLES', '', 18, '2008-03-17', 'Female', NULL),
(108961130034, 'DIMAYUGA', 'JOHN RENZ', 'VALLESTEROS', '', 17, '2008-09-01', 'Male', NULL),
(108961130035, 'ENAD', 'JOHN CARLO', 'TULBO', '', 17, '2008-08-30', 'Male', NULL),
(108961130039, 'ORTEGA', 'PRINCE JOMAR', 'PERLADA', '', 18, '2008-01-19', 'Male', NULL),
(108961130041, 'ROCA', 'JOHN LLOYD', 'CARBAQUIL', '', 17, '2008-07-03', 'Male', NULL),
(108961130042, 'SILVALLANA', 'CHRISTIAN GABTIEL', 'PONCE', '', 18, '2007-12-25', 'Male', NULL),
(108961130043, 'ALCANTARA', 'BEA CLARIZ', 'CAPISTRANO', '', 18, '2008-04-02', 'Female', NULL),
(108961130045, 'BERDIDA', 'ERICA', 'VELEZ', '', 18, '2008-05-21', 'Female', NULL),
(108961130046, 'BACOLOR', 'JANZYNE KYLE', 'BAGALAY', '', 17, '2008-10-30', 'Female', NULL),
(108961130050, 'MONTANTE', 'IRISH JANE', 'DELLAVA', '', 18, '2007-11-12', 'Female', NULL),
(108961130051, 'ROSALES', 'MARIA CRISTINA', 'TACASTACAS', '', 18, '2007-12-25', 'Female', NULL),
(108961130054, 'VERGARA', 'JASMINE LORRAINE', 'FOLTUN', '', 17, '2008-09-14', 'Female', NULL),
(108961130055, 'URSABIA', 'LOVELY MAY', 'AREVALO', '', 18, '2008-05-18', 'Female', NULL),
(108961130058, 'LUCES', 'JOEMAR JAYDEN', 'MACAHILOS', '', 18, '2007-10-04', 'Male', NULL),
(108961140007, 'ESCOBAR', 'EARL JHON', 'EROLES', '', 17, '2008-12-09', 'Male', NULL),
(108961140008, 'FERMIS', 'MELVIN', 'HERMOSO', '', 17, '2009-04-18', 'Male', NULL),
(108961140011, 'DELA PE?A', 'MARK JUSTINE', 'EDA?O', '', 17, '2008-11-08', 'Male', NULL),
(108961140012, 'EROLES', 'ROY LEVI', 'OCAN', '', 16, '2009-10-22', 'Male', NULL),
(108961140014, 'MEDINA', 'ZANE JARED', 'CALUGAY', '', 16, '2009-10-27', 'Male', NULL),
(108961140015, 'NIERA', 'JAMES', 'MADRIAGA', '', 16, '2009-10-26', 'Male', NULL),
(108961140019, 'ABALOS', 'JENNY ROSE', 'UNTALAN', '', 17, '2009-04-20', 'Female', NULL),
(108961140020, 'ANGELES', 'ZHAMEL', 'DIAZ', '', 17, '2008-11-02', 'Female', NULL),
(108961140024, 'CARAEL', 'MARIANNE', 'DEDUYO', '', 17, '2009-05-09', 'Female', NULL),
(108961140026, 'ELEJIDO', 'ELIJAH JAIRA', 'MUEGA', '', 16, '2009-08-15', 'Female', NULL),
(108961140027, 'ESGUERRA', 'AUBREY CEANNA', 'ALEGRE', '', 17, '2009-03-04', 'Female', NULL),
(108961140028, 'FABIE', 'JAMAICA', 'BONEO', '', 16, '2009-09-13', 'Female', NULL),
(108961140029, 'FLORES', 'CHRISTINE GAYLE', 'UNTALAN', '', 17, '2008-12-17', 'Female', NULL),
(108961140030, 'FRANCISCO', 'AIRA MAE', 'MAGNO', '', 17, '2008-12-26', 'Female', NULL),
(108961140031, 'HONTIVEROS', 'JENNY ROSE', 'EROLES', '', 17, '2009-05-28', 'Female', NULL),
(108961140034, 'LIGANDO', 'KEIARA', 'EROLES', '', 16, '2009-09-17', 'Female', NULL),
(108961140039, 'RADAZA', 'ELCA NICOLE', 'AN', '', 17, '2008-11-10', 'Female', NULL),
(108961140040, 'RODRIGUEZ', 'PRECIOUS LYKA', 'LAZO', '', 16, '2009-09-19', 'Female', NULL),
(108961140045, 'ILAO', 'MARK KIAN', 'VELASQUEZ', '', 17, '2008-11-10', 'Male', NULL),
(108961140048, 'PAREDES', 'GWEN ADRIEL', 'YANORIA', '', 17, '2009-04-29', 'Male', NULL),
(108961140049, 'PLANAS', 'JOHN DAIVE', 'ROLDAN', '', 17, '2009-02-25', 'Male', NULL),
(108961140054, 'AGUILAR', 'LOUISSE ANGELA', 'EROLES', '', 16, '2009-07-16', 'Female', NULL),
(108961140055, 'CAMBA', 'ARTCHIE', 'FERMIS', '', 17, '2009-02-13', 'Female', NULL),
(108961140058, 'LACABA', 'ABEGAIL', 'VERGANIO', '', 17, '2009-04-08', 'Female', NULL),
(108961140059, 'MONDOY', 'ROXANNE', 'VELASCO', '', 17, '2008-12-03', 'Female', NULL),
(108961140064, 'POLO', 'VANESSA', 'VERSOZA', '', 16, '2009-09-25', 'Female', NULL),
(108961140073, 'LIGANDO', 'KEIREN JOY', 'EROLES', '', 18, '2008-06-16', 'Female', NULL),
(108961140075, 'AGUIBA', 'NOAH', 'OCFEMIA', '', 18, '2008-05-22', 'Male', NULL),
(108961140077, 'ALTEZ', 'NOEL', 'SALLUTAN', '', 17, '2008-10-28', 'Male', NULL),
(108961140093, 'AGUILA', 'JHON LLOYD', 'BINALLA', '', 17, '2008-08-20', 'Male', NULL),
(108961140101, 'PRIETO', 'DOMINIK', 'VALENZUELA', '', 17, '2008-08-24', 'Male', NULL),
(108961150002, 'ALTEZ', 'RALP JOHN', 'CAMBA', '', 15, '2010-09-27', 'Male', NULL),
(108961150006, 'VALDERAMA', 'MARVIN', 'BUEDRON', 'JR', 16, '2010-05-23', 'Male', NULL),
(108961150009, 'CALDERON', 'JELAINE KHYLA', 'GUIBANE', '', 15, '2010-10-10', 'Female', NULL),
(108961150012, 'DE GUZMAN', 'GINEVHE', 'GUIBANE', '', 17, '2009-07-17', 'Female', NULL),
(108961150013, 'DE LA CRUZ', 'JOY', 'UNTALAN', '', 16, '2010-03-02', 'Female', NULL),
(108961150014, 'DELA PE?A', 'HELARY KIM', 'EDA?O', '', 15, '2010-09-11', 'Female', NULL),
(108961150015, 'EROLES', 'LISETTE', 'QUINTO', '', 15, '2010-10-27', 'Female', NULL),
(108961150016, 'GARUFIL', 'WINALYN', 'EROLES', '', 16, '2009-11-15', 'Female', NULL),
(108961150017, 'OCAN', 'KATRINA', 'EROLES', '', 16, '2009-11-08', 'Female', NULL),
(108961150018, 'SEVILLENA', 'JANELLE', 'ROGELIO', '', 16, '2009-10-14', 'Female', NULL),
(108961150019, 'VERGARA', 'ALEXA', 'FULTON', '', 16, '2010-08-02', 'Female', NULL),
(108961150020, 'LANNA', 'JHON CARLO', 'LUNA', '', 16, '2010-01-26', 'Male', NULL),
(108961150021, 'AGUIBA', 'JOHN BENEDICT', 'ROBLES', '', 16, '2010-07-02', 'Male', NULL),
(108961150023, 'CAMBARIHAN', 'JHON JAIRUS', 'GLORIOSO', '', 16, '2010-06-26', 'Male', NULL),
(108961150024, 'DEGRAS', 'GEO ALDREX', 'CAMBARIHAN', '', 15, '2010-08-26', 'Male', NULL),
(108961150025, 'LASQUETY', 'IAN MARK', 'ABLA?A', '', 16, '2010-04-03', 'Male', NULL),
(108961150026, 'LIMOS', 'CARL JOAQUIN', 'REYES', '', 16, '2010-05-28', 'Male', NULL),
(108961150029, 'ABAYON', 'REYNALYN', 'AQUILES', '', 16, '2009-12-13', 'Female', NULL),
(108961150030, 'AGUIBA', 'JENNY ROSE', 'ALIMAN', '', 16, '2010-03-06', 'Female', NULL),
(108961150031, 'BARCELONA', 'JAN KRISTA', 'FURIGAY', '', 16, '2010-01-02', 'Female', NULL),
(108961150032, 'CAMBA', 'AIZA MAY', 'GLORIOSO', '', 16, '2010-05-15', 'Female', NULL),
(108961150033, 'DELGADO', 'ILYN', 'BALDOS', '', 16, '2010-01-23', 'Female', NULL),
(108961150036, 'ILAGAN', 'BEA', 'SANCHEZ', '', 16, '2010-02-01', 'Female', NULL),
(108961150039, 'PONPON', 'MARY ANN', 'TOLING', '', 15, '2010-10-05', 'Female', NULL),
(108961150040, 'GARUFIL', 'CAILEINE MAE', 'ORFILA', '', 16, '2009-11-20', 'Female', NULL),
(108961150042, 'BALIWAG', 'ARJEAN', 'MENDOZA', '', 15, '2010-10-09', 'Female', NULL),
(108961150045, 'YTANG', 'JONALYN', 'URSAL', '', 17, '2008-08-29', 'Female', NULL),
(108961150047, 'CODILLA', 'LIRAMIL', 'CANTRE', '', 15, '2010-09-16', 'Female', NULL),
(108961150053, 'GATDULA', 'RAIN DENNIS', 'VALDEZ', '', 16, '2010-06-25', 'Male', NULL),
(108961150055, 'GUTIERREZ', 'DHANREX', 'GATDULA', '', 16, '2010-08-12', 'Male', NULL),
(108961150057, 'MARIENTES', 'EDIE BOY', 'CAPAROSO', '', 15, '2010-09-15', 'Male', NULL),
(108961150059, 'ROCA', 'MA LUISA', 'CARBAQUIL', '', 16, '2010-04-05', 'Female', NULL),
(108961150061, 'RODEJO', 'ROMMEL', 'LACERNA', '', 15, '2010-09-23', 'Male', NULL),
(108961150066, 'ZINAMPAN', 'JOHN PAUL', 'CORRAL', '', 16, '2010-07-06', 'Male', NULL),
(108961150067, 'DE LA PE?A', 'MARGELINE', 'VILLARUEL', '', 16, '2009-12-03', 'Female', NULL),
(108961150068, 'NIERA', 'DARWIN', 'BERAL', '', 15, '2010-10-14', 'Male', NULL),
(108961150074, 'HONTIVEROS', 'CHARLES DAVID', 'LOGATOC', '', 17, '2009-05-22', 'Male', NULL),
(108961150075, 'HONTIVEROS', 'CHARLES JAMES', 'LOGATOC', '', 17, '2009-05-22', 'Male', NULL),
(108961150078, 'MAGNO', 'LYKA JEAN', 'DEGRAS', '', 16, '2009-09-25', 'Female', NULL),
(108961160001, 'VILLARUEL', 'JAKE', 'VALLADOLID', '', 15, '2011-03-28', 'Male', NULL),
(108961160002, 'JASMIN', 'DAVE', 'BAUTISTA', '', 15, '2011-06-23', 'Male', NULL),
(108961160003, 'RADAZA', 'CHRISTIAN ERIC', 'AN', '', 15, '2010-11-24', 'Male', NULL),
(108961160005, 'EROLES', 'SALVE REGINA', 'TUMOLIN', '', 15, '2010-11-05', 'Female', NULL),
(108961160006, 'EROLES', 'FRINCE JOBERT', 'FLORES', '', 15, '2010-11-20', 'Male', NULL),
(108961160007, 'ILAO', 'MARIEL', 'DE CASTRO', '', 15, '2010-08-25', 'Female', NULL),
(108961160009, 'VERGANIO', 'CHRISTINE JOY ANN', 'EROLES', '', 15, '2010-12-13', 'Female', NULL),
(108961160011, 'SEVILLENA', 'JOHN PATRICK', 'ROGELIO', '', 15, '2010-11-07', 'Male', NULL),
(108961160016, 'EROLES', 'CHARLIE JAY', 'VERGANIO', '', 15, '2011-01-14', 'Male', NULL),
(108961160020, 'MALINIZA', 'ALADIN', 'GUAB', '', 17, '2009-02-17', 'Male', NULL),
(108961160024, 'CARAEL', 'IVY JANE', 'DEDUYO', '', 15, '2010-11-18', 'Female', NULL),
(108961160025, 'DONES', 'QUEEN CAYLIE', 'ANGELES', '', 15, '2011-05-23', 'Female', NULL),
(108961160027, 'FRAGO', 'NHELA ZENALOU', 'PEDERNAL', '', 15, '2011-04-21', 'Female', NULL),
(108961160028, 'MALINIZA', 'DANICA MAE', 'AGATON', '', 16, '2010-04-22', 'Female', NULL),
(108961160029, 'EROLES', 'XYRHEX', 'MAAT', '', 15, '2010-09-13', 'Male', NULL),
(108961160031, 'HECITA', 'EDRIAN', 'EROLES', '', 15, '2011-04-22', 'Male', NULL),
(108961160035, 'MONLEON', 'YZA MYELLA', 'QUINTO', '', 15, '2011-06-12', 'Female', NULL),
(108961160036, 'NEPOMUCENO', 'KATE', 'AMANTE', '', 15, '2011-04-09', 'Female', NULL),
(108961160037, 'FERMIS', 'MAYBELINE', 'HERMOSO', '', 15, '2011-05-20', 'Female', NULL),
(108961160038, 'VERGANIO', 'MARK ANGELO', 'CABRERA', '', 15, '2011-06-03', 'Male', NULL),
(108961160047, 'CAMBA', 'CARLO', 'FERMIS', '', 15, '2010-11-08', 'Male', NULL),
(108961160048, 'PACAYRA', 'CHRISTEL', 'DISONGLO', '', 15, '2011-05-15', 'Female', NULL),
(108961160049, 'ALOTA', 'SARRAH JHEN', 'BAUTISTA', '', 15, '2010-12-28', 'Female', NULL),
(108961160051, 'ARAZO', 'REA MAE', 'PENSADER', '', 15, '2011-01-21', 'Female', NULL),
(108961160053, 'CLEMENTE', 'MECHAELA JANE', 'BAUTISTA', '', 15, '2011-05-31', 'Female', NULL),
(108961160054, 'BAUTISTA', 'JESSRYL', 'TEROL', '', 15, '2010-12-22', 'Male', NULL),
(108961170001, 'CAMBARIHAN', 'RENZ JEO', 'GLORIOSO', '', 14, '2012-03-09', 'Male', NULL),
(108961170003, 'ANGCAJAS', 'EDDIE', 'MALINAO', 'JR', 14, '2012-04-21', 'Male', NULL),
(108961170004, 'NOCIETE', 'NOAH', 'ILAGAN', '', 14, '2011-12-23', 'Male', NULL),
(108961170006, 'MONDOY', 'DENNIS', 'MADRIGAL', 'JR', 14, '2012-01-15', 'Male', NULL),
(108961170013, 'DEGRAS', 'HANS JETRO', 'FERMIS', '', 15, '2011-08-18', 'Male', NULL),
(108961170014, 'DEGRAS', 'JHON ALLEN', 'PIA', '', 15, '2011-07-16', 'Male', NULL),
(108961170015, 'ILAO', 'EMMANUEL', 'CASAO', '', 15, '2011-08-01', 'Male', NULL),
(108961170016, 'QUINCENA', 'PRINCE GABRIEL', 'ARDALES', '', 14, '2011-11-24', 'Male', NULL),
(108961170018, 'RADAZA', 'KEVIN CREILLE', 'AN', '', 14, '2012-04-22', 'Male', NULL),
(108961170019, 'DELIN', 'NAETHAN QUIEL', '', '', 14, '2011-09-19', 'Male', NULL),
(108961170021, 'VERGARA', 'ROWJAN', 'FULTON', '', 14, '2012-04-20', 'Male', NULL),
(108961170022, 'BONDE', 'JOHN ELRAY', 'URTULA', '', 15, '2011-08-15', 'Male', NULL),
(108961170023, 'CRUZAT', 'JURELLE ANNE', 'CAMBA', '', 14, '2012-04-28', 'Female', NULL),
(108961170024, 'DELA PE?A', 'AUBREY ANNE', 'QUINCENA', '', 14, '2012-04-17', 'Female', NULL),
(108961170025, 'DELGADO', 'JANNAH ALIYAH', 'CABAGONG', '', 14, '2012-03-15', 'Female', NULL),
(108961170026, 'DELIMA', 'AVRIL RHEIAN', 'EROLES', '', 14, '2012-01-22', 'Female', NULL),
(108961170027, 'GONZALES', 'ATASHIA MHAE', 'PIEDAD', '', 14, '2011-10-27', 'Female', NULL),
(108961170029, 'MONDOY', 'ANGELINE', 'VELASCO', '', 14, '2011-11-16', 'Female', NULL),
(108961170030, 'MONTANTE', 'MARIA ANTONIA', 'DELLAVA', '', 14, '2012-03-30', 'Female', NULL),
(108961170031, 'NERA', 'KATHERINE', 'ESCOTO', '', 14, '2012-01-28', 'Female', NULL),
(108961170032, 'NIEVA', 'BERYL FAITH', 'FABELLON', '', 15, '2011-08-04', 'Female', NULL),
(108961170033, 'PALLAT', 'ALIYAH JANE', 'ENDAYA', '', 14, '2011-10-09', 'Female', NULL),
(108961170034, 'PAREDES', 'MITTZ FHRENZYN', 'YANORIA', '', 14, '2011-10-27', 'Female', NULL),
(108961170037, 'VERGARA', 'AMILA JOY', 'ALANO', '', 14, '2011-08-29', 'Female', NULL),
(108961170038, 'SAMARISTA', 'JAHNIA MARVIE', 'OFLARIA', '', 15, '2011-08-13', 'Female', NULL),
(108961170039, 'VILLAREAL', 'JHANNA ANN', 'DEGRAS', '', 14, '2012-02-23', 'Female', NULL),
(108961170041, 'PE?AFLOR', 'PRINCESS  SYRILLE', 'MEDINA', '', 14, '2012-02-10', 'Female', NULL),
(108961170043, 'CAMBA', 'ROS JEZREEL', 'GLORIOSO', '', 14, '2011-11-23', 'Male', NULL),
(108961170044, 'GLEE', 'ROBERT', 'DELA ROCA', 'JR', 14, '2011-11-24', 'Male', NULL),
(108961170046, 'CODILLA', 'JOHN RAMLEE', 'CANTRE', '', 14, '2012-01-04', 'Male', NULL),
(108961170048, 'ABAYON', 'REYMARK', 'AQUILES', '', 14, '2011-10-22', 'Male', NULL),
(108961170050, 'FERMIS', 'MARK LOUIE', 'REFORSADO', '', 14, '2011-10-05', 'Male', NULL),
(108961170052, 'MANALO', 'MARK POUL', 'RODRIGUEZ', '', 14, '2012-01-24', 'Male', NULL),
(108961170054, 'MARTALLA', 'JIHM MADDY', 'DECELIS', '', 14, '2012-02-20', 'Male', NULL),
(108961170056, 'PERALTA', 'REYMARK', 'LLABRIZ', '', 14, '2011-11-18', 'Male', NULL),
(108961170057, 'PONPON', 'MELDIV', 'TOLING', '', 14, '2012-04-12', 'Male', NULL),
(108961170059, 'SEPATO', 'MARK GABRIEL', 'VENDER', '', 14, '2011-08-24', 'Male', NULL),
(108961170060, 'SEPATO', 'MARK DANIEL', 'VENDER', '', 14, '2011-08-24', 'Male', NULL),
(108961170061, 'SILVALLANA', 'ANGELO', 'GUEVARRA', '', 14, '2011-11-22', 'Male', NULL),
(108961170062, 'TOLEDO', 'ARNOLD', 'ESPINOSA', '', 15, '2011-02-15', 'Male', NULL),
(108961170063, 'BAGAYAS', 'ZOLENN AITHANA', 'RED', '', 14, '2011-09-28', 'Female', NULL),
(108961170064, 'CAAYON', 'STEPHANIE KHATE', 'CARBAQUEL', '', 14, '2012-05-22', 'Female', NULL),
(108961170066, 'ILAO', 'PAULINE', 'VELASQUEZ', '', 15, '2011-05-29', 'Female', NULL),
(108961170069, 'MERCADEJAS', 'TRISHA MAE', 'BERZUELA', '', 14, '2011-09-10', 'Female', NULL),
(108961170070, 'PALOMARES', 'ERIKA', 'BILLIONES', '', 14, '2012-01-05', 'Female', NULL),
(108961170075, 'DONES', 'TRIXIE', 'VENDER', '', 14, '2011-11-14', 'Female', NULL),
(108961170077, 'ESCOBAR', 'EUNELAINE', 'EROLES', '', 14, '2011-10-10', 'Female', NULL),
(108961170080, 'ORCALES', 'JEREMIAS', 'ESPINOSA', 'JR', 15, '2010-08-30', 'Male', NULL),
(108961180003, 'BAUTISTA', 'MARK JOSEPH', 'AGUIRRE', '', 13, '2012-11-17', 'Male', NULL),
(108961180009, 'FERMIS', 'ANDRIE', 'MADERA', '', 13, '2012-11-11', 'Male', NULL),
(108961180010, 'FRIAS', 'JOHN EDRIC', 'EROLES', '', 13, '2012-11-05', 'Male', NULL),
(108961180012, 'GLORIOSO', 'RAFAEL', 'DE LOS REYES', '', 14, '2012-07-19', 'Male', NULL),
(108961180013, 'INOY', 'CHERSON', 'FABIE', '', 14, '2012-08-09', 'Male', NULL),
(108961180014, 'MAUSIG', 'XYRUS EARL', 'ROGELIO', '', 13, '2012-12-22', 'Male', NULL),
(108961180016, 'PALLAT', 'ROY VINCE', 'ENDAYA', '', 13, '2013-01-03', 'Male', NULL),
(108961180017, 'BAUTISTA', 'EZEQUEL II', 'PLANAS', '', 13, '2013-04-07', 'Male', NULL),
(108961180018, 'CODILLA', 'JOHN MARK ANGELO', '', '', 13, '2013-05-20', 'Male', NULL),
(108961180020, 'DONES', 'CURTH JUSTINE', 'VENDER', '', 13, '2013-02-18', 'Male', NULL),
(108961180022, 'PACAYRA', 'ANGELITO', 'DISONGLO', '', 13, '2013-05-21', 'Male', NULL),
(108961180023, 'PENTINIO', 'XAICEN MATTHEW', 'CASTILLO', '', 13, '2013-07-15', 'Male', NULL),
(108961180024, 'PERILIA', 'JOHN CARLO', 'VASQUEZ', '', 13, '2013-05-03', 'Male', NULL),
(108961180025, 'SAN LUIS', 'JOHN LARRY', 'MELENDRES', '', 13, '2012-11-11', 'Male', NULL),
(108961180029, 'DELA CRUZ', 'JHANNA MARIEL', 'DECELIS', '', 13, '2013-04-26', 'Female', NULL),
(108961180032, 'EDERON', 'ELLARIE KRISEL', 'QUINTO', '', 13, '2012-12-05', 'Female', NULL),
(108961180033, 'ELLA', 'PATRICIA NICOLE', 'VENDER', '', 13, '2012-12-29', 'Female', NULL),
(108961180035, 'EROLES', 'VERONICA', 'ORINOCO', '', 13, '2012-12-06', 'Female', NULL),
(108961180036, 'FABIE', 'MICA ELLA', 'BONEO', '', 14, '2012-05-19', 'Female', NULL),
(108961180037, 'FAJARDO', 'DEA MAE', 'NERA', '', 13, '2013-04-29', 'Female', NULL),
(108961180038, 'JASMIN', 'ELLA MAE', 'BAUTISTA', '', 13, '2013-02-17', 'Female', NULL),
(108961180039, 'MARQUEZ', 'RACHELLE', 'CUETO', '', 13, '2013-05-20', 'Female', NULL),
(108961180049, 'ILAO', 'CHARLOTTE', 'CASAO', '', 13, '2013-04-04', 'Female', NULL),
(108961180050, 'SILVALLANA', 'ANDREA', 'GUEVARRA', '', 13, '2013-07-17', 'Female', NULL),
(108961180051, 'VENDER', 'BABY TRIXCY', 'ROGELIO', '', 13, '2013-03-10', 'Female', NULL),
(108961180052, 'GASTARDO', 'JERICO JADE', 'LEPALAM', '', 13, '2013-07-06', 'Male', NULL),
(108961180053, 'HONTIVEROS', 'JAY MARK', 'VILLAREAL', '', 14, '2012-08-07', 'Male', NULL),
(108961180056, 'EROLES', 'CYRA JHANE', 'VERGANIO', '', 13, '2012-12-12', 'Female', NULL),
(108961180059, 'NIERA', 'MARK ANGELO', 'BERAL', '', 13, '2013-03-16', 'Male', NULL),
(108971140187, 'AYAPANA', 'GABRIEL', 'PUREZA', '', 18, '2008-06-26', 'Male', NULL),
(109059130011, 'BOHOL', 'ROSE ANNE', 'BAJAR', '', 17, '2008-09-18', 'Female', NULL),
(109095150119, 'VERZOSA', 'ANGEL', 'SANCHEZ', '', 17, '2009-03-07', 'Female', NULL),
(109095150137, 'VERZOSA', 'MIGUEL', 'SANCHEZ', '', 16, '2010-01-26', 'Male', NULL),
(109114140067, 'ROCAS', 'BRENOLD', 'ROVERO', '', 18, '2007-11-17', 'Male', NULL),
(109123170005, 'BALATUCAN', 'CHERVIN', 'OCTOMAN', '', 15, '2011-06-05', 'Male', NULL),
(109133160050, 'BANDA', 'JOHN RUSSEL', 'SANTOS', '', 15, '2011-01-29', 'Male', NULL),
(109163170146, 'HUSADA', 'CHERRY MAE', 'ORIEL', '', 14, '2011-11-05', 'Female', NULL),
(109323140306, 'ZINAMPAN', 'ROMEL', 'CORRAL', '', 18, '2008-05-26', 'Male', NULL),
(109337160272, 'ABAYON', 'JHIAN CHESKA', 'TOLENTINO', '', 15, '2010-10-02', 'Female', NULL),
(109542180066, 'GONZAGA', 'REGINE', 'FABIE', '', 14, '2012-02-22', 'Female', NULL),
(109582170036, 'DEL MUNDO', 'EJAY', 'ACU?A', '', 15, '2011-05-17', 'Male', NULL),
(109725150007, 'PE?ARUBIA', 'CYRUS JOHN', 'MIRADOR', '', 16, '2009-10-26', 'Male', NULL),
(109740160117, 'JUDILLA', 'MARINIEL', 'PORNELA', '', 15, '2011-01-22', 'Female', NULL),
(109798140116, 'VILLAREAL', 'RUSSEL', 'BOLA?OS', '', 18, '2008-08-21', 'Male', NULL),
(111537140054, 'BOLINAS', 'AUDREY ROSE', 'MENDOZA', '', 17, '2009-03-23', 'Female', NULL),
(111591110025, 'BERDIN', 'JUSTIN', 'DE GUZMAN', '', 20, '2006-04-03', 'Male', NULL),
(112621120678, 'DOMINGO', 'PRINCESS LEA', 'BAROTILLO', '', 18, '2007-10-18', 'Female', NULL),
(113036170036, 'BUTIAL', 'JAYMAR', 'DATA', '', 15, '2011-08-20', 'Male', NULL),
(120020140132, 'TIMTIM', 'JILLIAN', 'CAMARA', '', 17, '2009-08-22', 'Female', NULL),
(136431150207, 'SAMBAHON', 'JOHN DAVID', 'DAO', '', 17, '2009-06-30', 'Male', NULL),
(136526160421, 'TUTOR', 'JOHN CHRISMAR', 'LUCES', '', 16, '2010-04-02', 'Male', NULL),
(136600150339, 'GARIN', 'JUN MICHAEL', 'YANGAT', '', 15, '2010-09-08', 'Male', NULL),
(226001140569, 'VERGANIO', 'DANIEL JAMES', 'CABRERA', '', 17, '2009-03-16', 'Male', NULL),
(226001140591, 'VERGANIO', 'PETER JAMES', 'CABRERA', '', 17, '2009-03-16', 'Male', NULL),
(300763100167, 'DE LEON', 'JOSHUA', 'NOYNAY', '', 18, '2007-12-26', 'Male', NULL),
(402431150444, 'JORDAN', 'MIKHAELA DAPHNE', 'DEGRAS', '', 16, '2009-11-03', 'Female', NULL),
(402431150458, 'JORDAN', 'MIGZ DARREN', 'DEGRAS', '', 17, '2008-09-06', 'Male', NULL),
(402786180009, 'CALAMUCHA', 'NATHANIEL', 'QUERUBIN', '', 14, '2012-05-15', 'Male', NULL),
(403255170011, 'VILLAREAL', 'KHIAN GABRIEL', 'ARAUJO', '', 15, '2011-07-09', 'Male', NULL),
(403259152036, 'ZABALLERO', 'THEODORE IVAN', 'ANYAYAHAN', '', 17, '2009-07-09', 'Male', NULL),
(424854180047, 'LABORA', 'CLARK KENT', 'PALOMADO', '', 13, '2013-07-04', 'Male', NULL),
(500566170226, 'SAMBAHON', 'CZARINA MAE', 'DAO', '', 14, '2012-04-02', 'Female', NULL),
(500566180093, 'SAMBAHON', 'MARY JANE', 'DAO', '', 12, '2013-08-27', 'Female', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `time_settings`
--

CREATE TABLE `time_settings` (
  `id` int(11) NOT NULL,
  `morning_start` time NOT NULL,
  `morning_end` time NOT NULL,
  `morning_late_threshold` time NOT NULL,
  `afternoon_start` time NOT NULL,
  `afternoon_end` time NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `allow_mon` tinyint(1) NOT NULL DEFAULT 1,
  `allow_tue` tinyint(1) NOT NULL DEFAULT 1,
  `allow_wed` tinyint(1) NOT NULL DEFAULT 1,
  `allow_thu` tinyint(1) NOT NULL DEFAULT 1,
  `allow_fri` tinyint(1) NOT NULL DEFAULT 1,
  `allow_sat` tinyint(1) NOT NULL DEFAULT 0,
  `allow_sun` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_settings`
--

INSERT INTO `time_settings` (`id`, `morning_start`, `morning_end`, `morning_late_threshold`, `afternoon_start`, `afternoon_end`, `updated_at`, `allow_mon`, `allow_tue`, `allow_wed`, `allow_thu`, `allow_fri`, `allow_sat`, `allow_sun`) VALUES
(1, '17:17:00', '17:20:00', '17:19:00', '17:21:00', '17:22:00', '2026-01-06 09:16:46', 1, 1, 1, 1, 1, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','faculty') NOT NULL,
  `status` varchar(20) DEFAULT 'inactive'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `status`) VALUES
(1, 'odnimerjeffreyd@gmail.com', '123', 'admin', 'active');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `advisers`
--
ALTER TABLE `advisers`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `lrn` (`lrn`),
  ADD KEY `enrollment_id` (`enrollment_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enrollment_id`),
  ADD KEY `lrn` (`lrn`);

--
-- Indexes for table `faculty_login`
--
ALTER TABLE `faculty_login`
  ADD PRIMARY KEY (`faculty_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `guardians`
--
ALTER TABLE `guardians`
  ADD PRIMARY KEY (`guardian_id`),
  ADD KEY `lrn` (`lrn`);

--
-- Indexes for table `rfid`
--
ALTER TABLE `rfid`
  ADD PRIMARY KEY (`rfid_id`),
  ADD UNIQUE KEY `lrn` (`lrn`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`section_id`),
  ADD UNIQUE KEY `unique_employee_per_grade` (`employee_id`,`grade_level`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`lrn`);

--
-- Indexes for table `time_settings`
--
ALTER TABLE `time_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `enrollments`
--
ALTER TABLE `enrollments`
  MODIFY `enrollment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1036;

--
-- AUTO_INCREMENT for table `faculty_login`
--
ALTER TABLE `faculty_login`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guardians`
--
ALTER TABLE `guardians`
  MODIFY `guardian_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rfid`
--
ALTER TABLE `rfid`
  MODIFY `rfid_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=341;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `time_settings`
--
ALTER TABLE `time_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`),
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`enrollment_id`) REFERENCES `enrollments` (`enrollment_id`);

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`);

--
-- Constraints for table `faculty_login`
--
ALTER TABLE `faculty_login`
  ADD CONSTRAINT `faculty_login_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `advisers` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `guardians`
--
ALTER TABLE `guardians`
  ADD CONSTRAINT `guardians_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`);

--
-- Constraints for table `rfid`
--
ALTER TABLE `rfid`
  ADD CONSTRAINT `rfid_ibfk_1` FOREIGN KEY (`lrn`) REFERENCES `students` (`lrn`);

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `advisers` (`employee_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
