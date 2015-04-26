SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS `account`;
CREATE TABLE `account` (
  `account_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `github_id` INT UNSIGNED NOT NULL,
  `login` VARCHAR(150) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(255) NOT NULL,
  `avatar_url` TINYTEXT NOT NULL,
  `html_url` TINYTEXT NOT NULL,
  `company` VARCHAR(150) NULL,
  `location` VARCHAR(250) NULL,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `account_githubid_uk` (`github_id`),
  UNIQUE KEY `account_email_uk` (`email`)
) ENGINE=InnoDb CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';

DROP TABLE IF EXISTS `interest`;
CREATE TABLE `interest` (
  `interest_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `interest` VARCHAR(25) NOT NULL,
  PRIMARY KEY (`interest_id`),
  UNIQUE KEY `interest_label_uk` (`interest`)
) ENGINE=InnoDb CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';
INSERT INTO `interest` (`interest`) VALUES ('apprentice'),('mentor');

DROP TABLE IF EXISTS `tag`;
CREATE TABLE `tag` (
  `tag_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag` VARCHAR(25) NOT NULL,
  PRIMARY KEY (`tag_id`),
  UNIQUE KEY `tag_label_uk` (`tag`)
) ENGINE=InnoDb CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';
INSERT INTO `tag` (`tag`) VALUES ('php'),('oop'),('spl');

DROP TABLE IF EXISTS `account_interests`;
CREATE TABLE `account_interests` (
  `account_id` INT UNSIGNED NOT NULL,
  `interest_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`account_id`, `interest_id`),
  FOREIGN KEY `ai_account_fk` (`account_id`)
    REFERENCES `account` (`account_id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE,
  FOREIGN KEY `ai_interest_fk` (`interest_id`)
    REFERENCES `interest` (`interest_id`)
      ON DELETE CASCADE
      ON UPDATE CASCADE
) ENGINE=InnoDb CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';

DROP TABLE IF EXISTS `account_tags`;
CREATE TABLE `account_tags` (
  `account_id` INT UNSIGNED NOT NULL,
  `tag_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`account_id`, `tag_id`),
  FOREIGN KEY `at_account_fk` (`account_id`)
    REFERENCES `account` (`account_id`)
      ON UPDATE CASCADE
      ON DELETE CASCADE,
  FOREIGN KEY `at_tag_fk` (`tag_id`)
    REFERENCES `tag` (`tag_id`)
      ON UPDATE CASCADE
      ON DELETE CASCADE
) ENGINE=InnoDb CHARACTER SET 'utf8' COLLATE 'utf8_general_ci';
SET FOREIGN_KEY_CHECKS=1;
