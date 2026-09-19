<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\math\Vector3;
use pocketmine\network\mcpe\NetworkBinaryStream;
use pocketmine\utils\Color;

/**
 * @see PrimitiveShapesPacket
 */
final class PacketShapeData{

	public function __construct(
		private int $networkId,
		private ?int $type,
		private ?Vector3 $location,
		private ?float $scale,
		private ?Vector3 $rotation,
		private ?float $totalTimeLeft,
		private ?float $maximumRenderDistance,
		private ?Color $color,
		private ?string $text,
		private ?bool $useRotation,
		private ?Color $backgroundColor,
		private ?int $lineGapHeight,
		private ?bool $depthTest,
		private ?bool $showBackface,
		private ?bool $showTextBackface,
		private ?Vector3 $boxBound,
		private ?Vector3 $lineEndLocation,
		private ?float $arrowHeadLength,
		private ?float $arrowHeadRadius,
		private ?int $segments,
		private ?int $dimensionId,
		private ?int $attachedToEntityId,
	){
	}

	public static function remove(int $networkId, ?int $dimensionId = null) : self{
		return new self($networkId, null, null, null, null, null, null, null, null, null, null, 0, null, null, null, null, null, null, null, null, $dimensionId, null);
	}

	public static function line(int $networkId, Vector3 $location, Vector3 $lineEndLocation, ?Color $color = null, ?int $dimensionId = null, ?int $attachedToEntityId = null) : self{
		return new self(
			networkId: $networkId,
			type: ScriptDebugShapeType::LINE,
			location: $location,
			scale: null,
			rotation: null,
			totalTimeLeft: null,
			maximumRenderDistance: null,
			color: $color,
			text: null,
			useRotation: null,
			backgroundColor: null,
			lineGapHeight: null,
			depthTest: null,
			showBackface: null,
			showTextBackface: null,
			boxBound: null,
			lineEndLocation: $lineEndLocation,
			arrowHeadLength: null,
			arrowHeadRadius: null,
			segments: null,
			dimensionId: $dimensionId,
			attachedToEntityId: $attachedToEntityId
		);
	}

	public static function box(int $networkId, Vector3 $location, Vector3 $boxBound, ?float $scale = null, ?Color $color = null, ?int $dimensionId = null, ?int $attachedToEntityId = null) : self{
		return new self(
			networkId: $networkId,
			type: ScriptDebugShapeType::BOX,
			location: $location,
			scale: $scale,
			rotation: null,
			totalTimeLeft: null,
			maximumRenderDistance: null,
			color: $color,
			text: null,
			useRotation: null,
			backgroundColor: null,
			lineGapHeight: null,
			depthTest: null,
			showBackface: null,
			showTextBackface: null,
			boxBound: $boxBound,
			lineEndLocation: null,
			arrowHeadLength: null,
			arrowHeadRadius: null,
			segments: null,
			dimensionId: $dimensionId,
			attachedToEntityId: $attachedToEntityId
		);
	}

	public static function sphere(int $networkId, Vector3 $location, ?float $scale = null, ?Color $color = null, ?int $segments = null, ?int $dimensionId = null, ?int $attachedToEntityId = null) : self{
		return new self(
			networkId: $networkId,
			type: ScriptDebugShapeType::SPHERE,
			location: $location,
			scale: $scale,
			rotation: null,
			totalTimeLeft: null,
			maximumRenderDistance: null,
			color: $color,
			text: null,
			useRotation: null,
			backgroundColor: null,
			lineGapHeight: null,
			depthTest: null,
			showBackface: null,
			showTextBackface: null,
			boxBound: null,
			lineEndLocation: null,
			arrowHeadLength: null,
			arrowHeadRadius: null,
			segments: $segments,
			dimensionId: $dimensionId,
			attachedToEntityId: $attachedToEntityId
		);
	}

	public static function circle(int $networkId, Vector3 $location, ?float $scale = null, ?Color $color = null, ?int $segments = null, ?int $dimensionId = null, ?int $attachedToEntityId = null) : self{
		return new self(
			networkId: $networkId,
			type: ScriptDebugShapeType::CIRCLE,
			location: $location,
			scale: $scale,
			rotation: null,
			totalTimeLeft: null,
			maximumRenderDistance: null,
			color: $color,
			text: null,
			useRotation: null,
			backgroundColor: null,
			lineGapHeight: null,
			depthTest: null,
			showBackface: null,
			showTextBackface: null,
			boxBound: null,
			lineEndLocation: null,
			arrowHeadLength: null,
			arrowHeadRadius: null,
			segments: $segments,
			dimensionId: $dimensionId,
			attachedToEntityId: $attachedToEntityId
		);
	}

	public static function text(int $networkId, Vector3 $location, string $text, bool $useRotation = false, ?Color $backgroundColor = null, int $lineGapHeight = 1, bool $depthTest = true, bool $showBackface = true, bool $showTextBackface = true, ?Color $color = null, ?int $dimensionId = null, ?int $attachedToEntityId = null) : self{
		return new self(
			networkId: $networkId,
			type: ScriptDebugShapeType::TEXT,
			location: $location,
			scale: null,
			rotation: null,
			totalTimeLeft: null,
			maximumRenderDistance: null,
			color: $color,
			text: $text,
			useRotation: $useRotation,
			backgroundColor: $backgroundColor,
			lineGapHeight: $lineGapHeight,
			depthTest: $depthTest,
			showBackface: $showBackface,
			showTextBackface: $showTextBackface,
			boxBound: null,
			lineEndLocation: null,
			arrowHeadLength: null,
			arrowHeadRadius: null,
			segments: null,
			dimensionId: $dimensionId,
			attachedToEntityId: $attachedToEntityId
		);
	}

	public static function arrow(int $networkId, Vector3 $location, Vector3 $lineEndLocation, ?float $scale = null, ?Color $color = null, ?float $arrowHeadLength = null, ?float $arrowHeadRadius = null, ?int $segments = null, ?int $dimensionId = null, ?int $attachedToEntityId = null) : self{
		return new self(
			networkId: $networkId,
			type: ScriptDebugShapeType::ARROW,
			location: $location,
			scale: $scale,
			rotation: null,
			totalTimeLeft: null,
			maximumRenderDistance: null,
			color: $color,
			text: null,
			useRotation: null,
			backgroundColor: null,
			lineGapHeight: null,
			depthTest: null,
			showBackface: null,
			showTextBackface: null,
			boxBound: null,
			lineEndLocation: $lineEndLocation,
			arrowHeadLength: $arrowHeadLength,
			arrowHeadRadius: $arrowHeadRadius,
			segments: $segments,
			dimensionId: $dimensionId,
			attachedToEntityId: $attachedToEntityId
		);
	}

	public function getNetworkId() : int{ return $this->networkId; }

	public function getType() : ?int{ return $this->type; }

	public function getLocation() : ?Vector3{ return $this->location; }

	public function getScale() : ?float{ return $this->scale; }

	public function getRotation() : ?Vector3{ return $this->rotation; }

	public function getTotalTimeLeft() : ?float{ return $this->totalTimeLeft; }

	public function getMaximumRenderDistance() : ?float{ return $this->maximumRenderDistance; }

	public function getColor() : ?Color{ return $this->color; }

	public function getText() : ?string{ return $this->text; }

	public function getUseRotation() : ?bool{ return $this->useRotation; }

	public function getBackgroundColor() : ?Color{ return $this->backgroundColor; }

	public function getDepthTest() : ?bool{ return $this->depthTest; }

	public function getShowBackface() : ?bool{ return $this->showBackface; }

	public function getShowTextBackface() : ?bool{ return $this->showTextBackface; }

	public function getBoxBound() : ?Vector3{ return $this->boxBound; }

	public function getLineEndLocation() : ?Vector3{ return $this->lineEndLocation; }

	public function getArrowHeadLength() : ?float{ return $this->arrowHeadLength; }

	public function getArrowHeadRadius() : ?float{ return $this->arrowHeadRadius; }

	public function getSegments() : ?int{ return $this->segments; }

	public function getDimensionId() : ?int{ return $this->dimensionId; }

	public function getAttachedToEntityId() : ?int{ return $this->attachedToEntityId; }

	public static function read(NetworkBinaryStream $in) : self{
		$networkId = $in->getUnsignedVarLong();
		$type = $in->readOptional(fn() => $in->getByte());
		$location = $in->readOptional(fn() => $in->getVector3());
		$scale = $in->readOptional(fn() => $in->getLFloat());
		$rotation = $in->readOptional(fn() => $in->getVector3());
		$totalTimeLeft = $in->readOptional(fn() => $in->getLFloat());
		$maximumRenderDistance = $in->readOptional(fn() => $in->getLFloat());
		$color = $in->readOptional(fn() => Color::fromARGB($in->getLInt()));
		$dimensionId = $in->readOptional(fn() => $in->getVarInt());
		$attachedToEntityId = $in->readOptional(fn() => $in->getEntityRuntimeId());

		$payloadType = $in->getUnsignedVarInt();

		$text = null;
		$useRotation = null;
		$backgroundColor = null;
		$lineGapHeight = null;
		$depthTest = null;
		$showBackface = null;
		$showTextBackface = null;
		$boxBound = null;
		$lineEndLocation = null;
		$arrowHeadLength = null;
		$arrowHeadRadius = null;
		$segments = null;

		switch($payloadType){
			case ScriptDebugShapeType::PAYLOAD_TYPE_NONE:
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_ARROW:
				$lineEndLocation = $in->readOptional(fn() => $in->getVector3());
				$arrowHeadLength = $in->readOptional(fn() => $in->getLFloat());
				$arrowHeadRadius = $in->readOptional(fn() => $in->getLFloat());
				$segments = $in->readOptional(fn() => $in->getByte());
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_TEXT:
				$text = $in->getString();
				$useRotation = $in->getBool();
				$backgroundColor = $in->readOptional(fn() => Color::fromARGB($in->getLInt()));
				$lineGapHeight = $in->getLInt();
				$depthTest = $in->getBool();
				$showBackface = $in->getBool();
				$showTextBackface = $in->getBool();
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_BOX:
				$boxBound = $in->getVector3();
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_LINE:
				$lineEndLocation = $in->getVector3();
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_CIRCLE_OR_SPHERE:
				$segments = $in->getByte();
				break;
		}

		return new self(
			$networkId, $type, $location, $scale, $rotation, $totalTimeLeft, $maximumRenderDistance,
			$color, $text, $useRotation, $backgroundColor, $lineGapHeight, $depthTest, $showBackface, $showTextBackface,
			$boxBound, $lineEndLocation, $arrowHeadLength, $arrowHeadRadius, $segments, $dimensionId, $attachedToEntityId
		);
	}

	public function write(NetworkBinaryStream $out) : void{
		$out->putUnsignedVarLong($this->networkId);
		$out->writeOptional($this->type, fn(int $type) => $out->putByte($type));
		$out->writeOptional($this->location, fn(Vector3 $location) => $out->putVector3($location));
		$out->writeOptional($this->scale, fn(float $scale) => $out->putLFloat($scale));
		$out->writeOptional($this->rotation, fn(Vector3 $rotation) => $out->putVector3($rotation));
		$out->writeOptional($this->totalTimeLeft, fn(float $totalTimeLeft) => $out->putLFloat($totalTimeLeft));
		$out->writeOptional($this->maximumRenderDistance, fn(float $dist) => $out->putLFloat($dist));
		$out->writeOptional($this->color, fn(Color $color) => $out->putLInt($color->toARGB()));
		$out->writeOptional($this->dimensionId, fn(int $dim) => $out->putVarInt($dim));
		$out->writeOptional($this->attachedToEntityId, fn(int $eid) => $out->putEntityRuntimeId($eid));

		$payloadType = $this->type !== null ? ScriptDebugShapeType::getPayloadType($this->type) : ScriptDebugShapeType::PAYLOAD_TYPE_NONE;
		$out->putUnsignedVarInt($payloadType);

		switch($payloadType){
			case ScriptDebugShapeType::PAYLOAD_TYPE_ARROW:
				$out->writeOptional($this->lineEndLocation, fn(Vector3 $v) => $out->putVector3($v));
				$out->writeOptional($this->arrowHeadLength, fn(float $f) => $out->putLFloat($f));
				$out->writeOptional($this->arrowHeadRadius, fn(float $f) => $out->putLFloat($f));
				$out->writeOptional($this->segments, fn(int $i) => $out->putByte($i));
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_TEXT:
				$out->putString($this->text ?? "");
				$out->putBool($this->useRotation ?? false);
				$out->writeOptional($this->backgroundColor, fn(Color $c) => $out->putLInt($c->toARGB()));
				$out->putLInt($this->lineGapHeight ?? 0);
				$out->putBool($this->depthTest ?? true);
				$out->putBool($this->showBackface ?? true);
				$out->putBool($this->showTextBackface ?? true);
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_BOX:
				$out->putVector3($this->boxBound ?? new Vector3(0, 0, 0));
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_LINE:
				$out->putVector3($this->lineEndLocation ?? new Vector3(0, 0, 0));
				break;
			case ScriptDebugShapeType::PAYLOAD_TYPE_CIRCLE_OR_SPHERE:
				$out->putByte($this->segments ?? 0);
				break;
			default:
				throw new \LogicException("Unknown payload type $payloadType");
		}
	}
}