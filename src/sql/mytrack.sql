-- MySQL Script generated by MySQL Workbench
-- Mon Jan 31 06:38:42 2022
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
  `device_name` VARCHAR(100) NULL,
  PRIMARY KEY (`iddevice`))
ENGINE = InnoDB;


-- -----------------------------------------------------
-- Table `loc`
-- -----------------------------------------------------
DROP TABLE IF EXISTS `loc` ;

CREATE TABLE IF NOT EXISTS `loc` (
  `idloc` INT NOT NULL AUTO_INCREMENT,
  `loc_device` INT NULL,
  `loc_timestamp` DATETIME NULL,
  `loc_lat` DOUBLE NULL,
  `loc_lon` DOUBLE NULL,
  `loc_height` DOUBLE NULL,
  `loc_hdop` DOUBLE NULL,
  PRIMARY KEY (`idloc`),
  UNIQUE INDEX `index2` (`loc_device` ASC, `loc_timestamp` ASC),
  CONSTRAINT `fk_loc_device`
    FOREIGN KEY (`loc_device`)
    REFERENCES `device` (`iddevice`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION)
ENGINE = InnoDB;


SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
