-- MySQL Script generated by MySQL Workbench
-- Fri Feb 11 09:50:38 2022
-- Model: New Model    Version: 1.0
-- MySQL Workbench Forward Engineering

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Schema mytrack
-- -----------------------------------------------------

-- -----------------------------------------------------
-- Table `device`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `device` ;

CREATE TABLE IF NOT EXISTS `device` (
  `iddevice` INT NOT NULL AUTO_INCREMENT,
  `device_uuid` VARCHAR(32) NULL,
  `device_serial` INT NULL,
  `device_created` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `device_ip_address` VARCHAR(45) NULL,
  `device_name` VARCHAR(100) NULL,
  `device_last_hello` DATETIME NULL,
  `device_last_fix_status` TINYINT NULL,
  `device_last_fix_status_timestmap` DATETIME NULL,
  PRIMARY KEY (`iddevice`),
  UNIQUE INDEX `device_uuid_UNIQUE` (`device_uuid` ASC))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `trip`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `trip` ;

CREATE TABLE IF NOT EXISTS `trip` (
  `idtrip` INT NOT NULL AUTO_INCREMENT,
  `trip_start` DATETIME NULL,
  `trip_end` DATETIME NULL,
  `trip_device` INT NULL,
  `trip_name` VARCHAR(100) NULL,
  PRIMARY KEY (`idtrip`),
  INDEX `fk_trip_device1_idx` (`trip_device` ASC),
  INDEX `index3` (`trip_device` ASC, `trip_start` ASC),
  CONSTRAINT `fk_trip_device1`
    FOREIGN KEY (`trip_device`)
    REFERENCES `device` (`iddevice`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `loc`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `loc` ;

CREATE TABLE IF NOT EXISTS `loc` (
  `idloc` INT NOT NULL AUTO_INCREMENT,
  `loc_device` INT NULL,
  `loc_timestamp` DATETIME NULL,
  `loc_serial` INT NULL,
  `loc_lat` DOUBLE NULL,
  `loc_lon` DOUBLE NULL,
  `loc_height` DOUBLE NULL,
  `loc_hdop` DOUBLE NULL,
  `loc_trip` INT NULL,
  PRIMARY KEY (`idloc`),
  UNIQUE INDEX `index2` (`loc_device` ASC, `loc_timestamp` ASC),
  UNIQUE INDEX `index3` (`loc_device` ASC, `loc_serial` ASC),
  INDEX `fk_loc_trip1_idx` (`loc_trip` ASC),
  INDEX `index5` (`loc_device` ASC, `loc_trip` ASC, `loc_timestamp` ASC),
  CONSTRAINT `fk_loc_device`
    FOREIGN KEY (`loc_device`)
    REFERENCES `device` (`iddevice`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_loc_trip1`
    FOREIGN KEY (`loc_trip`)
    REFERENCES `trip` (`idtrip`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `audit`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `audit` ;

CREATE TABLE IF NOT EXISTS `audit` (
  `idaudit` INT NOT NULL AUTO_INCREMENT,
  `audit_device` INT NULL,
  `audit_type` VARCHAR(45) NULL,
  `audit_timestamp` DATETIME NULL DEFAULT CURRENT_TIMESTAMP,
  `audit_description` VARCHAR(500) NULL,
  PRIMARY KEY (`idaudit`),
  INDEX `fk_audit_device1_idx` (`audit_device` ASC),
  CONSTRAINT `fk_audit_device1`
    FOREIGN KEY (`audit_device`)
    REFERENCES `device` (`iddevice`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
