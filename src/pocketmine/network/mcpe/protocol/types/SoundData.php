<?php

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use LogicException;
use pocketmine\network\mcpe\NetworkBinaryStream;
use UnexpectedValueException;

class SoundData{

	public function __construct(
		/** @see SoundDataType */
		private int $type,
		private ?float $volume = null,
		private ?float $pitch = null,
		private ?float $duration = null,
		private ?float $targetVolume = null,
		private ?float $seconds = null
	){}

	public function getType() : int{ return $this->type; }
	public function getVolume() : ?float{ return $this->volume; }
	public function getPitch() : ?float{ return $this->pitch; }
	public function getDuration() : ?float{ return $this->duration; }
	public function getTargetVolume() : ?float{ return $this->targetVolume; }
	public function getSeconds() : ?float{ return $this->seconds; }

	public static function stop() : self{
		return new self(SoundDataType::STOP);
	}

	public static function setVolume(float $volume) : self{
		return new self(SoundDataType::SET_VOLUME, volume: $volume);
	}

	public static function setPitch(float $pitch) : self{
		return new self(SoundDataType::SET_PITCH, pitch: $pitch);
	}

	public static function fade(float $duration, float $targetVolume) : self{
		return new self(SoundDataType::FADE, duration: $duration, targetVolume: $targetVolume);
	}

	public static function seekTo(float $seconds) : self{
		return new self(SoundDataType::SEEK_TO, seconds: $seconds);
	}

	public static function pause() : self{
		return new self(SoundDataType::PAUSE);
	}

	public static function resume() : self{
		return new self(SoundDataType::RESUME);
	}

	public static function read(NetworkBinaryStream $in) : self{
		$type = $in->getLInt();

		return match($type){
			SoundDataType::STOP => self::stop(),
			SoundDataType::PAUSE => self::pause(),
			SoundDataType::RESUME => self::resume(),
			SoundDataType::SET_VOLUME => self::setVolume($in->getLFloat()),
			SoundDataType::SET_PITCH => self::setPitch($in->getLFloat()),
			SoundDataType::FADE => self::fade($in->getLFloat(), $in->getLFloat()),
			SoundDataType::SEEK_TO => self::seekTo($in->getLFloat()),
			default => throw new UnexpectedValueException("Unknown SoundDataType: $type")
		};
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->putLInt($this->type);
		switch($this->type){
			case SoundDataType::STOP:
			case SoundDataType::PAUSE:
			case SoundDataType::RESUME:
				break;
			case SoundDataType::SET_VOLUME:
				if($this->volume === null){
					throw new LogicException("SoundDataEvent with type SET_VOLUME requires volume");
				}
				$out->putLFloat($this->volume);
				break;
			case SoundDataType::SET_PITCH:
				if($this->pitch === null){
					throw new LogicException("SoundDataEvent with type SET_PITCH requires pitch");
				}
				$out->putLFloat($this->pitch);
				break;
			case SoundDataType::FADE:
				if($this->duration === null || $this->targetVolume === null){
					throw new LogicException("SoundDataEvent with type FADE requires duration and targetVolume");
				}
				$out->putLFloat($this->duration);
				$out->putLFloat($this->targetVolume);
				break;
			case SoundDataType::SEEK_TO:
				if($this->seconds === null){
					throw new LogicException("SoundDataEvent with type SEEK_TO requires seconds");
				}
				$out->putLFloat($this->seconds);
				break;
		}
	}
}