# UPDATING.md

This document describes the process for updating **BetterAltay** to support a new Minecraft Bedrock release. Minecraft's network protocol changes frequently, so keeping the server compatible requires careful inspection and testing.

---

## 1. Checking protocol changes

Start by reviewing the official protocol documentation and pick the branch for the target game version:

* [Mojang/bedrock-protocol-docs](https://github.com/Mojang/bedrock-protocol-docs)

Because the official docs can sometimes be incomplete or contain mistakes, cross-check with additional sources:

* [Kaooot/Protocol](https://github.com/Kaooot/Protocol)
* [altayofficial/BedrockProtocol](https://github.com/altayofficial/BedrockProtocol)
* [axolotl-pm/BedrockProtocol](https://github.com/axolotl-pm/BedrockProtocol)

Compare these repositories to determine which packets or fields were added, removed or modified.

---

## 2. Updating ProtocolInfo

Usually the file

```
BetterAltay/src/pocketmine/network/mcpe/protocol/ProtocolInfo.php
```

needs updating when the protocol changes.

* Bump the `CURRENT_PROTOCOL` constant to the new protocol version.
* Update `MINECRAFT_VERSION_NETWORK` to the correct game version string.

---

## 3. Adding or updating packets

* New packet classes can be added under `src/pocketmine/network/mcpe/protocol/` for packets introduced in the latest protocol. At a minimum, implement the new packets that are necessary for gameplay and client joinability. You are free to add others.
* For modified packets, update `encodePayload()` and `decodePayload()` so the encoding/decoding matches the new spec.
* Also update any other parts of the code that use these packets, to match the changes
* Adjust constants to fit the new version.
* Remove packets that no longer exist in the protocol to avoid confusion.

---

## 4. Updating game data

When Mojang introduces new content, several server-side data files must often be refreshed. The most important ones include:

* **`item_palette.json`** Can be taken from [Kaooot/bedrock-network-data](https://github.com/Kaooot/bedrock-network-data) or [altayofficial/BedrockData](https://github.com/altayofficial/BedrockData)
* **`r16_to_current_item_map.json`** – Either updated manually or taken from [altayofficial/BedrockData](https://github.com/altayofficial/BedrockData).
* **`item_components.nbt`** Obtained from [Kaooot/bedrock-network-data](https://github.com/Kaooot/bedrock-network-data) or [altayofficial/BedrockData](https://github.com/altayofficial/BedrockData)
* **`creative_items.json`** – Can be pulled from [Kaooot/bedrock-network-data](https://github.com/Kaooot/bedrock-network-data/) or [altayofficial/BedrockData](https://github.com/altayofficial/BedrockData).

* **`block_palette.nbt`** – Taken from [altayofficial/BedrockData](https://github.com/altayofficial/BedrockData) or [Kaooot/bedrock-network-data](https://github.com/Kaooot/bedrock-network-data/) or [CloudburstMC/Data](https://github.com/CloudburstMC/Data).
* **`r12_to_current_block_map.json`** – Updated by https://github.com/BetterAltayBedrock/r12-map-updater if needed.

* **`stripped_biome_definitions.json`** – Obtainable from [CloudburstMC/Data](https://github.com/CloudburstMC/Data) or generated via ProxyPass.

* **`level_sound_id_map.json`** – Either updated by [extract_sound_map.py](https://gist.github.com/Benedikt05/977080670a15c8532ec3f1b82032d1d6) or taken from [altayofficial/BedrockData](https://github.com/altayofficial/BedrockData).

---

## 5. Testing the update

Test against the latest Bedrock client version:

1. Start a BetterAltay server from your updated branch.
2. Join with a stable client matching the target protocol.
3. Test block breaking and whatever you need to playtest.

If the client disconnects or crashes, you will need to investigate packet mismatches or unexpected behavior to identify the issue.

---

## 6. Final notes

* Expect surprises: Mojang sometimes changes the protocol without increasing the protocol version.
* While most updates are straightforward and can be handled using these instructions, some may require advanced protocol knowledge and the guide alone might not be sufficient.

---

##
