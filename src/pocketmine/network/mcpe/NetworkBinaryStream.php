<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe;

#include <rules/DataPacket.h>

use Closure;
use pocketmine\block\BlockIds;
use pocketmine\entity\Attribute;
use pocketmine\entity\Entity;
use pocketmine\item\Durable;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\nbt\LittleEndianNBTStream;
use pocketmine\nbt\NetworkLittleEndianNBTStream;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\NamedTag;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\ItemTypeDictionary;
use pocketmine\network\mcpe\convert\RuntimeBlockMapping;
use pocketmine\network\mcpe\protocol\types\CommandOriginData;
use pocketmine\network\mcpe\protocol\types\EntityLink;
use pocketmine\network\mcpe\protocol\types\GameRuleType;
use pocketmine\network\mcpe\protocol\types\PersonaPieceTintColor;
use pocketmine\network\mcpe\protocol\types\PersonaSkinPiece;
use pocketmine\network\mcpe\protocol\types\SkinAnimation;
use pocketmine\network\mcpe\protocol\types\SkinData;
use pocketmine\network\mcpe\protocol\types\SkinImage;
use pocketmine\network\mcpe\protocol\types\StructureEditorData;
use pocketmine\network\mcpe\protocol\types\StructureSettings;
use pocketmine\utils\BinaryStream;
use pocketmine\utils\Binary;
use pocketmine\utils\UUID;
use UnexpectedValueException;
use function assert;
use function count;
use function strlen;

class NetworkBinaryStream extends BinaryStream{

	private const DAMAGE_TAG = "Damage"; //TAG_Int
	private const DAMAGE_TAG_CONFLICT_RESOLUTION = "___Damage_ProtocolCollisionResolution___";
	private const PM_META_TAG = "___Meta___";

	public function getString() : string{
		return $this->get($this->getUnsignedVarInt());
	}

	public function putString(string $v) : void{
		$this->putUnsignedVarInt(strlen($v));
		$this->put($v);
	}

	public function getUUID() : UUID{
		//This is actually two little-endian longs: UUID Most followed by UUID Least
		$part1 = $this->getLInt();
		$part0 = $this->getLInt();
		$part3 = $this->getLInt();
		$part2 = $this->getLInt();

		return new UUID($part0, $part1, $part2, $part3);
	}

	public function putUUID(UUID $uuid) : void{
		$this->putLInt($uuid->getPart(1));
		$this->putLInt($uuid->getPart(0));
		$this->putLInt($uuid->getPart(3));
		$this->putLInt($uuid->getPart(2));
	}

	public function getSkin() : SkinData{
		$skinId = $this->getString();
		$skinResourcePatch = $this->getString();
		$skinData = $this->getSkinImage();
		$animationCount = $this->getUnsignedVarInt();
		$animations = [];
		for($i = 0; $i < $animationCount; ++$i){
			$skinImage = $this->getSkinImage();
			$animationType = $this->getUnsignedVarInt();
			$animationFrames = $this->getLFloat();
			$expressionType = $this->getUnsignedVarInt();
			$animations[] = new SkinAnimation($skinImage, $animationType, $animationFrames, $expressionType);
		}
		$capeData = $this->getSkinImage();
		$geometryData = $this->getString();
		$geometryDataVersion = $this->getString();
		$animationData = $this->getString();
		$capeId = $this->getString();
		$fullSkinId = $this->getString();
		$armSize = $this->getByte();
		$skinColor = $this->getLInt();
		$personaPieceCount = $this->getUnsignedVarInt();
		$personaPieces = [];
		for($i = 0; $i < $personaPieceCount; ++$i){
			$pieceId = $this->getString();
			$pieceType = $this->getLInt();
			$packId = $this->getUUID();
			$isDefaultPiece = $this->getBool();
			$productId = $this->getString();
			$personaPieces[] = new PersonaSkinPiece($pieceId, $pieceType, $packId, $isDefaultPiece, $productId);
		}
		$pieceTintColorCount = $this->getUnsignedVarInt();
		$pieceTintColors = [];
		for($i = 0; $i < $pieceTintColorCount; ++$i){
			$pieceType = $this->getString();
			$colors = [];
			for($j = 0; $j < 4; ++$j){
				$colors[] = $this->getLInt();
			}
			$pieceTintColors[] = new PersonaPieceTintColor(
				$pieceType,
				$colors
			);
		}
		$premium = $this->getBool();
		$persona = $this->getBool();
		$capeOnClassic = $this->getBool();
		$isPrimaryUser = $this->getBool();
		$override = $this->getBool();
		$trustedSkinFlag = $this->getString();
		$profileHash = $this->getString();

		return new SkinData($skinId, $skinResourcePatch, $skinData, $animations, $capeData, $geometryData, $geometryDataVersion, $animationData, $capeId, $fullSkinId, $armSize, $skinColor, $personaPieces, $pieceTintColors, true, $premium, $persona, $capeOnClassic, $isPrimaryUser, $override, $trustedSkinFlag, $profileHash);
	}

	/**
	 * @return void
	 */
	public function putSkin(SkinData $skin){
		$this->putString($skin->getSkinId());
		$this->putString($skin->getResourcePatch());
		$this->putSkinImage($skin->getSkinImage());
		$this->putUnsignedVarInt(count($skin->getAnimations()));
		foreach($skin->getAnimations() as $animation){
			$this->putSkinImage($animation->getImage());
			$this->putUnsignedVarInt($animation->getType());
			$this->putLFloat($animation->getFrames());
			$this->putUnsignedVarInt($animation->getExpressionType());
		}
		$this->putSkinImage($skin->getCapeImage());
		$this->putString($skin->getGeometryData());
		$this->putString($skin->getGeometryDataEngineVersion());
		$this->putString($skin->getAnimationData());
		$this->putString($skin->getCapeId());
		$this->putString($skin->getFullSkinId());
		$this->putByte($skin->getArmSize());
		$this->putLInt($skin->getSkinColor());
		$this->putUnsignedVarInt(count($skin->getPersonaPieces()));
		foreach($skin->getPersonaPieces() as $piece){
			$this->putString($piece->getPieceId());
			$this->putLInt($piece->getPieceType());
			$this->putUUID($piece->getPackId());
			$this->putBool($piece->isDefaultPiece());
			$this->putString($piece->getProductId());
		}
		$this->putUnsignedVarInt(count($skin->getPieceTintColors()));
		foreach($skin->getPieceTintColors() as $tint){
			$this->putString($tint->getPieceType());
			foreach($tint->getColors() as $color){
				$this->putLInt($color);
			}
		}
		$this->putBool($skin->isPremium());
		$this->putBool($skin->isPersona());
		$this->putBool($skin->isPersonaCapeOnClassic());
		$this->putBool($skin->isPrimaryUser());
		$this->putBool($skin->isOverride());
		$this->putString($skin->getTrustedSkinFlag());
		$this->putString($skin->getProfileHash());
	}

	private function getSkinImage() : SkinImage{
		$width = $this->getLInt();
		$height = $this->getLInt();
		$data = $this->getString();

		return new SkinImage($height, $width, $data);
	}

	private function putSkinImage(SkinImage $image) : void{
		$this->putLInt($image->getWidth());
		$this->putLInt($image->getHeight());
		$this->putString($image->getData());
	}

	public function getItemStackWithoutStackId() : Item{
		return $this->getItemStack(function() : void{
			//NOOP
		}, false);
	}

	public function putItemStackWithoutStackId(Item $item) : void{
		$this->putItemStack($item, function() : void{
			//NOOP
		}, false);
	}

	/**
	 * @phpstan-param Closure(NetworkBinaryStream $stream, bool $net) : void $readStackId
	 */
	public function getItemStack(Closure $readStackId, bool $net) : Item{
		if($net){
			$netId = $this->getSignedLShort();
		}else{
			$netId = $this->getVarInt();
		}
		if($netId === 0){
			$this->getLShort(); //count
			$this->getUnsignedVarInt(); //damage
			$readStackId($this, $net);
			if($net){
				$this->getUnsignedVarInt(); //blockRuntimeId
			}else{
				$this->getVarInt(); //blockRuntimeId
			}
			$this->get($this->getUnsignedVarInt()); //nbt
			return ItemFactory::get(0, 0, 0);
		}

		$cnt = $this->getLShort();
		$netData = $this->getUnsignedVarInt();

		[$id, $meta] = ItemTranslator::getInstance()->fromNetworkId($netId, $netData);

		$readStackId($this, $net);

		if($net){
			$this->getUnsignedVarInt(); //blockRuntimeId
		}else{
			$this->getVarInt(); //blockRuntimeId
		}

		$extraData = new NetworkBinaryStream($this->getString());
		return (static function() use ($extraData, $netId, $id, $meta, $cnt) : Item{
			$nbtLen = $extraData->getLShort();

			/** @var CompoundTag|null $nbt */
			$nbt = null;
			if($nbtLen === 0xffff){
				$nbtDataVersion = $extraData->getByte();
				if($nbtDataVersion !== 1){
					throw new UnexpectedValueException("Unexpected NBT data version $nbtDataVersion");
				}
				$decodedNBT = (new LittleEndianNBTStream())->read($extraData->buffer, false, $extraData->offset, 512);
				if(!($decodedNBT instanceof CompoundTag)){
					throw new UnexpectedValueException("Unexpected root tag type for itemstack");
				}
				$nbt = $decodedNBT;
			}elseif($nbtLen !== 0){
				throw new UnexpectedValueException("Unexpected fake NBT length $nbtLen");
			}

			//TODO
			for($i = 0, $canPlaceOn = $extraData->getLInt(); $i < $canPlaceOn; ++$i){
				$extraData->get($extraData->getLShort());
			}

			//TODO
			for($i = 0, $canDestroy = $extraData->getLInt(); $i < $canDestroy; ++$i){
				$extraData->get($extraData->getLShort());
			}

			if($netId === ItemTypeDictionary::getInstance()->fromStringId("minecraft:shield")){
				$extraData->getLLong(); //"blocking tick" (ffs mojang)
			}

			if(!$extraData->feof()){
				throw new UnexpectedValueException("Unexpected trailing extradata for network item $netId");
			}

			if($nbt !== null){
				if($nbt->hasTag(self::DAMAGE_TAG, IntTag::class)){
					$meta = $nbt->getInt(self::DAMAGE_TAG);
					$nbt->removeTag(self::DAMAGE_TAG);
					if(($conflicted = $nbt->getTag(self::DAMAGE_TAG_CONFLICT_RESOLUTION)) !== null){
						$nbt->removeTag(self::DAMAGE_TAG_CONFLICT_RESOLUTION);
						$conflicted->setName(self::DAMAGE_TAG);
						$nbt->setTag($conflicted);
					}elseif($nbt->count() === 0){
						$nbt = null;
					}
				}elseif(($metaTag = $nbt->getTag(self::PM_META_TAG)) instanceof IntTag){
					//TODO HACK: This foul-smelling code ensures that we can correctly deserialize an item when the
					//client sends it back to us, because as of 1.16.220, blockitems quietly discard their metadata
					//client-side. Aside from being very annoying, this also breaks various server-side behaviours.
					$meta = $metaTag->getValue();
					$nbt->removeTag(self::PM_META_TAG);
					if($nbt->count() === 0){
						$nbt = null;
					}
				}
			}
			return ItemFactory::get($id, $meta, $cnt, $nbt);
		})();
	}

	/**
	 * @phpstan-param Closure(NetworkBinaryStream $stream, bool $net) : void $writeStackId
	 */
	public function putItemStack(Item $item, Closure $writeStackId, bool $net) : void{
		if($item->getId() === 0){
			if($net){
				$this->putLShort(0); //id
			}else{
				$this->putVarInt(0); //id
			}
			$this->putLShort(0); //count
			$this->putUnsignedVarInt(0); //damage
			$writeStackId($this, $net);
			if($net){
				$this->putUnsignedVarInt(0); //blockRuntimeId
			}else{
				$this->putVarInt(0); //blockRuntimeId
			}
			$this->putUnsignedVarInt(0); //userData Length
			return;
		}

		$coreData = $item->getDamage();
		[$netId, $netData] = ItemTranslator::getInstance()->toNetworkId($item->getId(), $coreData);

		if($net){
			$this->putLShort($netId);
		}else{
			$this->putVarInt($netId);
		}
		$this->putLShort($item->getCount());
		$this->putUnsignedVarInt($netData);

		$writeStackId($this, $net);

		$blockRuntimeId = 0;
		$isBlockItem = $item->getId() < 256;
		if($isBlockItem){
			$block = $item->getBlock();
			if($block->getId() !== BlockIds::AIR){
				$blockRuntimeId = $block->getRuntimeId();
			}
		}
		if($net){
			$this->putUnsignedVarInt(RuntimeBlockMapping::toStaticRuntimeHash($blockRuntimeId)); //blockRuntimeId
		}else{
			$this->putVarInt(RuntimeBlockMapping::toStaticRuntimeHash($blockRuntimeId)); //blockRuntimeId
		}
		$nbt = null;
		if($item->hasCompoundTag()){
			$nbt = clone $item->getNamedTag();
		}
		if($item instanceof Durable and $coreData > 0){
			if($nbt !== null){
				if(($existing = $nbt->getTag(self::DAMAGE_TAG)) !== null){
					$nbt->removeTag(self::DAMAGE_TAG);
					$existing->setName(self::DAMAGE_TAG_CONFLICT_RESOLUTION);
					$nbt->setTag($existing);
				}
			}else{
				$nbt = new CompoundTag();
			}
			$nbt->setInt(self::DAMAGE_TAG, $coreData);
		}elseif($isBlockItem && $coreData !== 0){
			//TODO HACK: This foul-smelling code ensures that we can correctly deserialize an item when the
			//client sends it back to us, because as of 1.16.220, blockitems quietly discard their metadata
			//client-side. Aside from being very annoying, this also breaks various server-side behaviours.
			if($nbt === null){
				$nbt = new CompoundTag();
			}
			$nbt->setInt(self::PM_META_TAG, $coreData);
		}

		$this->putString(
			(static function() use ($nbt, $netId) : string{
				$extraData = new NetworkBinaryStream();

				if($nbt !== null){
					$extraData->putLShort(0xffff);
					$extraData->putByte(1); //TODO: NBT data version (?)
					$extraData->put((new LittleEndianNBTStream())->write($nbt));
				}else{
					$extraData->putLShort(0);
				}

				$extraData->putLInt(0); //CanPlaceOn entry count (TODO)
				$extraData->putLInt(0); //CanDestroy entry count (TODO)

				if($netId === ItemTypeDictionary::getInstance()->fromStringId("minecraft:shield")){
					$extraData->putLLong(0); //"blocking tick" (ffs mojang)
				}
				return $extraData->getBuffer();
			})());
	}

	//TODO: this needs to be updated
	public function getRecipeIngredient() : Item{
		$netId = $this->getVarInt();
		if($netId === 0){
			return ItemFactory::get(ItemIds::AIR, 0, 0);
		}
		$netData = $this->getVarInt();
		[$id, $meta] = ItemTranslator::getInstance()->fromNetworkIdWithWildcardHandling($netId, $netData);
		$count = $this->getVarInt();
		return ItemFactory::get($id, $meta, $count);
	}

	public function putRecipeIngredient(Item $item) : void{
		$this->putUnsignedVarInt($item->isNull() ? 0 : 1);
		if($item->isNull()){
			$this->putVarInt(0x7fff);
			$this->putVarInt(0); //item count

			return;
		}

		$this->putString("name"); //ItemDescriptorType

		if($item->hasAnyDamageValue()){
			$netData = 0x7fff;
		}else{
			[, $netData] = ItemTranslator::getInstance()->toNetworkId($item->getId(), $item->getDamage());
		}

		//ItemDescriptor
		$this->putString(ItemTranslator::getInstance()->toStringId($item->getId(), $item->getDamage()));
		$this->putVarInt($netData);

		$this->putVarInt($item->getCount());
	}

	/**
	 * Decodes entity metadata from the stream.
	 *
	 * @param bool $types Whether to include metadata types along with values in the returned array
	 *
	 * @return mixed[]|mixed[][]
	 * @phpstan-return array<int, mixed>|array<int, array{0: int, 1: mixed}>
	 */
	public function getEntityMetadata(bool $types = true) : array{
		$count = $this->getUnsignedVarInt();
		$data = [];
		for($i = 0; $i < $count; ++$i){
			$key = $this->getUnsignedVarInt();
			$type = $this->getUnsignedVarInt();
			$this->getByte(); //type

			$value = null;
			switch($type){
				case Entity::DATA_TYPE_BYTE:
					$value = Binary::signByte($this->getByte());
					break;
				case Entity::DATA_TYPE_SHORT:
					$value = $this->getSignedLShort();
					break;
				case Entity::DATA_TYPE_INT:
					$value = $this->getVarInt();
					break;
				case Entity::DATA_TYPE_FLOAT:
					$value = $this->getLFloat();
					break;
				case Entity::DATA_TYPE_STRING:
					$value = $this->getString();
					break;
				case Entity::DATA_TYPE_COMPOUND_TAG:
					$value = (new NetworkLittleEndianNBTStream())->read($this->buffer, false, $this->offset, 512);
					break;
				case Entity::DATA_TYPE_POS:
					$value = new Vector3();
					$this->getBlockPosition($value->x, $value->y, $value->z);
					break;
				case Entity::DATA_TYPE_LONG:
					$value = $this->getVarLong();
					break;
				case Entity::DATA_TYPE_VECTOR3F:
					$value = $this->getVector3();
					break;
				default:
					throw new UnexpectedValueException("Invalid data type " . $type);
			}
			if($types){
				$data[$key] = [$type, $value];
			}else{
				$data[$key] = $value;
			}
		}

		return $data;
	}

	/**
	 * Writes entity metadata to the packet buffer.
	 *
	 * @param mixed[][]                                   $metadata
	 *
	 * @phpstan-param array<int, array{0: int, 1: mixed}> $metadata
	 */
	public function putEntityMetadata(array $metadata) : void{
		$this->putUnsignedVarInt(count($metadata));
		foreach($metadata as $key => $d){
			$this->putUnsignedVarInt($key); //data key
			$this->putUnsignedVarInt($d[0]); //data type
			$this->putByte($d[0]);

			switch($d[0]){
				case Entity::DATA_TYPE_BYTE:
					$this->putByte($d[1]);
					break;
				case Entity::DATA_TYPE_SHORT:
					$this->putLShort($d[1]); //SIGNED short!
					break;
				case Entity::DATA_TYPE_INT:
					$this->putVarInt($d[1]);
					break;
				case Entity::DATA_TYPE_FLOAT:
					$this->putLFloat($d[1]);
					break;
				case Entity::DATA_TYPE_STRING:
					$this->putString($d[1]);
					break;
				case Entity::DATA_TYPE_COMPOUND_TAG:
					$this->put((new NetworkLittleEndianNBTStream())->write($d[1]));
					break;
				case Entity::DATA_TYPE_POS:
					$v = $d[1];
					if($v !== null){
						$this->putBlockPosition($v->x, $v->y, $v->z);
					}else{
						$this->putBlockPosition(0, 0, 0);
					}
					break;
				case Entity::DATA_TYPE_LONG:
					$this->putVarLong($d[1]);
					break;
				case Entity::DATA_TYPE_VECTOR3F:
					$this->putVector3Nullable($d[1]);
					break;
				default:
					throw new UnexpectedValueException("Invalid data type " . $d[0]);
			}
		}
	}

	/**
	 * Reads a list of Attributes from the stream.
	 * @return Attribute[]
	 *
	 * @throws UnexpectedValueException if reading an attribute with an unrecognized name
	 */
	public function getAttributeList() : array{
		$list = [];
		$count = $this->getUnsignedVarInt();

		for($i = 0; $i < $count; ++$i){
			$min = $this->getLFloat();
			$max = $this->getLFloat();
			$current = $this->getLFloat();
			$default = $this->getLFloat();
			$name = $this->getString();

			$attr = Attribute::getAttributeByName($name);
			if($attr !== null){
				$attr->setMinValue($min);
				$attr->setMaxValue($max);
				$attr->setValue($current);
				$attr->setDefaultValue($default);

				$list[] = $attr;
			}else{
				throw new UnexpectedValueException("Unknown attribute type \"$name\"");
			}
		}

		return $list;
	}

	/**
	 * Writes a list of Attributes to the packet buffer using the standard format.
	 */
	public function putAttributeList(Attribute ...$attributes) : void{
		$this->putUnsignedVarInt(count($attributes));
		foreach($attributes as $attribute){
			$this->putLFloat($attribute->getMinValue());
			$this->putLFloat($attribute->getMaxValue());
			$this->putLFloat($attribute->getValue());
			$this->putLFloat($attribute->getMinValue()); //default min value
			$this->putLFloat($attribute->getMaxValue()); //default max value
			$this->putLFloat($attribute->getDefaultValue());
			$this->putString($attribute->getName());
			$this->putUnsignedVarInt(0); //Modifiers
		}
	}

	/**
	 * Reads and returns an EntityUniqueID
	 */
	final public function getEntityUniqueId() : int{
		return $this->getVarLong();
	}

	/**
	 * Writes an EntityUniqueID
	 */
	public function putEntityUniqueId(int $eid) : void{
		$this->putVarLong($eid);
	}

	/**
	 * Reads and returns an EntityRuntimeID
	 */
	final public function getEntityRuntimeId() : int{
		return $this->getUnsignedVarLong();
	}

	/**
	 * Writes an EntityRuntimeID
	 */
	public function putEntityRuntimeId(int $eid) : void{
		$this->putUnsignedVarLong($eid);
	}

	/**
	 * Reads a block position
	 *
	 * @param int $x reference parameter
	 * @param int $y reference parameter
	 * @param int $z reference parameter
	 */
	public function getBlockPosition(&$x, &$y, &$z) : void{
		$x = $this->getVarInt();
		$y = $this->getVarInt();
		$z = $this->getVarInt();
	}

	/**
	 * Writes a block position
	 */
	public function putBlockPosition(int $x, int $y, int $z) : void{
		$this->putVarInt($x);
		$this->putVarInt($y);
		$this->putVarInt($z);
	}

	/**
	 * Reads a floating-point Vector3 object with coordinates rounded to 4 decimal places.
	 */
	public function getVector3() : Vector3{
		$x = $this->getLFloat();
		$y = $this->getLFloat();
		$z = $this->getLFloat();
		return new Vector3($x, $y, $z);
	}

	/**
	 * Reads a floating-point Vector2 object with coordinates rounded to 4 decimal places.
	 */
	public function getVector2() : Vector2{
		$x = $this->getLFloat();
		$y = $this->getLFloat();
		return new Vector2($x, $y);
	}

	/**
	 * Writes a floating-point Vector3 object, or 3x zero if null is given.
	 *
	 * Note: ONLY use this where it is reasonable to allow not specifying the vector.
	 * For all other purposes, use the non-nullable version.
	 *
	 * @see NetworkBinaryStream::putVector3()
	 */
	public function putVector3Nullable(?Vector3 $vector) : void{
		if($vector !== null){
			$this->putVector3($vector);
		}else{
			$this->putLFloat(0.0);
			$this->putLFloat(0.0);
			$this->putLFloat(0.0);
		}
	}

	/**
	 * Writes a floating-point Vector3 object
	 */
	public function putVector3(Vector3 $vector) : void{
		$this->putLFloat($vector->x);
		$this->putLFloat($vector->y);
		$this->putLFloat($vector->z);
	}

	/**
	 * Writes a floating-point Vector2 object
	 */
	public function putVector2(Vector2 $vector2) : void{
		$this->putLFloat($vector2->x);
		$this->putLFloat($vector2->y);
	}

	public function getByteRotation() : float{
		return ($this->getByte() * (360 / 256));
	}

	public function putByteRotation(float $rotation) : void{
		$this->putByte((int) ($rotation / (360 / 256)));
	}

	/**
	 * Reads gamerules
	 * TODO: implement this properly
	 *
	 * @return mixed[][], members are in the structure [name => [type, value, isPlayerModifiable]]
	 * @phpstan-return array<string, array{0: int, 1: bool|int|float, 2: bool}>
	 */
	public function getGameRules() : array{
		$count = $this->getUnsignedVarInt();
		$rules = [];
		for($i = 0; $i < $count; ++$i){
			$name = $this->getString();
			$isPlayerModifiable = $this->getBool();
			$type = $this->getUnsignedVarInt();
			$value = null;
			switch($type){
				case GameRuleType::BOOL:
					$value = $this->getBool();
					break;
				case GameRuleType::INT:
					$value = $this->getLInt();
					break;
				case GameRuleType::FLOAT:
					$value = $this->getLFloat();
					break;
			}

			$rules[$name] = [$type, $value, $isPlayerModifiable];
		}

		return $rules;
	}

	/**
	 * Writes a gamerule array, members should be in the structure [name => [type, value, isPlayerModifiable]]
	 * TODO: implement this properly
	 *
	 * @param mixed[][]                                                        $rules
	 *
	 * @phpstan-param array<string, array{0: int, 1: bool|int|float, 2: bool}> $rules
	 */
	public function putGameRules(array $rules) : void{
		$this->putUnsignedVarInt(count($rules));
		foreach($rules as $name => $rule){
			$this->putString($name);
			$this->putBool($rule[2] ?? false);
			$this->putUnsignedVarInt($type = $rule[0]);
			switch($type){
				case GameRuleType::BOOL:
					$this->putBool($rule[1]);
					break;
				case GameRuleType::INT:
					$this->putLInt($rule[1]);
					break;
				case GameRuleType::FLOAT:
					$this->putLFloat($rule[1]);
					break;
			}
		}
	}

	protected function getEntityLink() : EntityLink{
		$fromEntityUniqueId = $this->getEntityUniqueId();
		$toEntityUniqueId = $this->getEntityUniqueId();
		$type = $this->getByte();
		$immediate = $this->getBool();
		$causedByRider = $this->getBool();
		$vehicleAngularVelocity = $this->getLFloat();
		return new EntityLink($fromEntityUniqueId, $toEntityUniqueId, $type, $immediate, $causedByRider, $vehicleAngularVelocity);
	}

	protected function putEntityLink(EntityLink $link) : void{
		$this->putEntityUniqueId($link->fromEntityUniqueId);
		$this->putEntityUniqueId($link->toEntityUniqueId);
		$this->putByte($link->type);
		$this->putBool($link->immediate);
		$this->putBool($link->causedByRider);
		$this->putLFloat($link->vehicleAngularVelocity);
	}

	protected function getCommandOriginData() : CommandOriginData{
		$result = new CommandOriginData();
		$result->type = $this->getString();
		$result->uuid = $this->getUUID();
		$result->requestId = $this->getString();
		$result->playerEntityUniqueId = $this->getLLong();

		return $result;
	}

	protected function putCommandOriginData(CommandOriginData $data) : void{
		$this->putString($data->type);
		$this->putUUID($data->uuid);
		$this->putString($data->requestId);
		$this->putLLong($data->playerEntityUniqueId);
	}

	protected function getStructureSettings() : StructureSettings{
		$result = new StructureSettings();

		$result->paletteName = $this->getString();

		$result->ignoreEntities = $this->getBool();
		$result->ignoreBlocks = $this->getBool();

		$this->getBlockPosition($result->structureSizeX, $result->structureSizeY, $result->structureSizeZ);
		$this->getBlockPosition($result->structureOffsetX, $result->structureOffsetY, $result->structureOffsetZ);

		$result->lastTouchedByPlayerID = $this->getEntityUniqueId();
		$result->rotation = $this->getByte();
		$result->mirror = $this->getByte();
		$result->animationMode = $this->getByte();
		$result->animationSeconds = $this->getLFloat();
		$result->integrityValue = $this->getLFloat();
		$result->integritySeed = $this->getLInt();
		$result->pivot = $this->getVector3();

		return $result;
	}

	protected function putStructureSettings(StructureSettings $structureSettings) : void{
		$this->putString($structureSettings->paletteName);

		$this->putBool($structureSettings->ignoreEntities);
		$this->putBool($structureSettings->ignoreBlocks);

		$this->putBlockPosition($structureSettings->structureSizeX, $structureSettings->structureSizeY, $structureSettings->structureSizeZ);
		$this->putBlockPosition($structureSettings->structureOffsetX, $structureSettings->structureOffsetY, $structureSettings->structureOffsetZ);

		$this->putEntityUniqueId($structureSettings->lastTouchedByPlayerID);
		$this->putByte($structureSettings->rotation);
		$this->putByte($structureSettings->mirror);
		$this->putByte($structureSettings->animationMode);
		$this->putLFloat($structureSettings->animationSeconds);
		$this->putLFloat($structureSettings->integrityValue);
		$this->putLInt($structureSettings->integritySeed);
		$this->putVector3($structureSettings->pivot);
	}

	protected function getStructureEditorData() : StructureEditorData{
		$result = new StructureEditorData();

		$result->structureName = $this->getString();
		$result->filterdStructureName = $this->getString();
		$result->structureDataField = $this->getString();

		$result->includePlayers = $this->getBool();
		$result->showBoundingBox = $this->getBool();

		$result->structureBlockType = $this->getVarInt();
		$result->structureSettings = $this->getStructureSettings();
		$result->structureRedstoneSaveMove = $this->getVarInt();

		return $result;
	}

	protected function putStructureEditorData(StructureEditorData $structureEditorData) : void{
		$this->putString($structureEditorData->structureName);
		$this->putString($structureEditorData->filterdStructureName);
		$this->putString($structureEditorData->structureDataField);

		$this->putBool($structureEditorData->includePlayers);
		$this->putBool($structureEditorData->showBoundingBox);

		$this->putVarInt($structureEditorData->structureBlockType);
		$this->putStructureSettings($structureEditorData->structureSettings);
		$this->putVarInt($structureEditorData->structureRedstoneSaveMove);
	}

	public function getNbtRoot() : NamedTag{
		$offset = $this->getOffset();
		try{
			$result = (new NetworkLittleEndianNBTStream())->read($this->getBuffer(), false, $offset, 512);
			assert($result instanceof NamedTag, "doMultiple is false so we should definitely have a NamedTag here");
			return $result;
		}finally{
			$this->setOffset($offset);
		}
	}

	public function getNbtCompoundRoot() : CompoundTag{
		$root = $this->getNbtRoot();
		if(!($root instanceof CompoundTag)){
			throw new UnexpectedValueException("Expected TAG_Compound root");
		}
		return $root;
	}

	public function readGenericTypeNetworkId() : int{
		return $this->getVarInt();
	}

	public function writeGenericTypeNetworkId(int $id) : void{
		$this->putVarInt($id);
	}

	/**
	 * @phpstan-template T
	 * @phpstan-param Closure() : T $reader
	 * @phpstan-return T|null
	 */
	public function readOptional(Closure $reader) : mixed{
		if($this->getBool()){
			return $reader();
		}
		return null;
	}

	/**
	 * @phpstan-template T
	 * @phpstan-param T|null            $value
	 * @phpstan-param Closure(T) : void $writer
	 */
	public function writeOptional(mixed $value, Closure $writer) : void{
		if($value !== null){
			$this->putBool(true);
			$writer($value);
		}else{
			$this->putBool(false);
		}
	}
}
