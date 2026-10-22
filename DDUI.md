# Introduction to DDUI

In short words DDUI is a reactive form menu. That means you can edit values within the menu without having to close it.

You can change a label, reset a slider, hide a button, or update a title while the player is still looking at the same form. Its pretty useful for settings menus and anything that needs a live status.

Here's how to use DDUI in a BetterAltay plugin, from a basic menu to a settings form with live inputs.

## Before you start

Import these classes in your plugin:

```php
use pocketmine\Player;
use pocketmine\ddui\DDUI;
use pocketmine\ddui\Observable;
```

The short examples below assume `$player` is an online `Player`. Run them on the server's main thread, usually from a command or an event.

Create a separate form for each player. Each player can have one DDUI form open at a time.

## Your first form

```php
$form = new DDUI($player, "Hello!");

$form
    ->label("This is my first DDUI form.")
    ->closeButton("Done");

$form->show();
```

Pass the player when creating the form, then call `show()` to open it. Use callbacks to handle clicks and closing.

There is one form class with different controls. You can mix buttons and inputs in the same menu.

## Observables

An `Observable` holds a value that can change. Pass the observable itself into a control if you want that control to update later.

```php
$status = new Observable("Waiting...");

$form = new DDUI($player, "Status");
$form->label($status)->closeButton();
$form->show();

// Later, from a callback or a main-thread task:
$status->setData("Ready!");
```

Read its current value with:

```php
$currentStatus = $status->getData();
```

For player-editable inputs, pass `true` as the second argument:

```php
$enabled = new Observable(true, true);
```

The first argument is the starting value. The second means the client is allowed to write to it when it is bound to an input. Labels and titles normally don't need that second argument.

Values can be strings, booleans or numbers. Keep the same type when updating them. Numbers may change between an integer and a float, since incoming numeric updates use doubles.

Don't pass `$status->getData()` when you want a live label. That only passes the current value, so it won't stay connected to the observable.

## Labels and headers

A label displays text. A header is useful for separating sections.

```php
$form = new DDUI($player, "Settings");
$form
    ->header("General")
    ->label("Change your settings below.");
$form->show();
```

Both accept a string or an observable:

```php
$heading = new Observable("General settings");
$message = new Observable("Nothing changed yet.");

$form = new DDUI($player, "Settings");
$form->header($heading)->label($message)->closeButton();
$form->show();

$heading->setData("Updated settings");
$message->setData("Your changes are ready.");
```

## Spacers

Use a spacer to leave some room between controls:

```php
$form = new DDUI($player, "Information");
$form
    ->label("First section")
    ->spacer()
    ->label("Second section")
    ->closeButton();
$form->show();
```

## Buttons

Buttons run a callback when clicked. They don't automatically close the form.

```php
$status = new Observable("No clicks yet.");
$clicks = 0;

$form = new DDUI($player, "Counter");
$form
    ->label($status)
    ->button("Click me", function(Player $player) use ($status, &$clicks) : void{
        ++$clicks;
        $status->setData("Clicked {$clicks} times.");
    }, [
        "tooltip" => "Updates the label without reopening the menu"
    ])
    ->closeButton();
$form->show();
```

The callback receives the player. Use `use (...)` to access variables from outside the callback. The counter uses `&$clicks` so it keeps the updated number between clicks.

The button label and `tooltip` can also be observables.

## Close buttons

```php
$form = new DDUI($player, "Example");
$form->label("All done?")->closeButton("Done");
$form->show();
```

Calling `closeButton()` without a label uses `Close`. It accepts only a label, and the button is always visible:

```php
$form = new DDUI($player, "Example");
$form->closeButton("Done");
$form->show();
```

The player can also close the form with Escape or Back. There is only one dedicated close button per form; calling `closeButton()` again replaces its label.

## Toggles

Toggles use a client-writable boolean:

```php
$particles = new Observable(true, true);

$form = new DDUI($player, "Preferences");
$form
    ->toggle("Show particles", $particles, [
        "description" => "Turn particle effects on or off"
    ])
    ->button("Read setting", function(Player $player) use ($particles) : void{
        $player->sendMessage($particles->getData() ? "Enabled" : "Disabled");
    })
    ->closeButton();
$form->show();
```

You can reset it from server code with `$particles->setData(false)`.

## Text fields

Text fields use a client-writable string:

```php
$nickname = new Observable($player->getName(), true);

$form = new DDUI($player, "Profile");
$form
    ->textField("Nickname", $nickname, [
        "description" => "Enter a display name",
        "maxBytes" => 32
    ])
    ->button("Read name", function(Player $player) use ($nickname) : void{
        $player->sendMessage("You entered: " . $nickname->getData());
    })
    ->closeButton();
$form->show();
```

`maxBytes` is a server-side byte limit, not a character counter or client keyboard limit. Some characters take more than one byte. It defaults to `1024` and accepts values from `1` to `32768`.

Validate the name and save it in your own plugin when the player confirms it.

## Sliders

The argument order is label, observable, minimum, maximum, step, then options.

```php
$volume = new Observable(50, true);

$form = new DDUI($player, "Audio");
$form
    ->slider("Volume", $volume, 0, 100, 5, [
        "description" => "Move in steps of 5"
    ])
    ->button("Read volume", function(Player $player) use ($volume) : void{
        $player->sendMessage("Volume: " . (int) $volume->getData());
    })
    ->closeButton();
$form->show();
```

Minimum, maximum and step are integers. Step defaults to `1` and must be positive. The starting value must be in range and aligned to a step from the minimum.

You can update the selected value with `setData()`. Keep server-written values valid too; the input checks don't automatically clamp them. Live changes to the range or step aren't exposed by this API.

## Dropdowns

Dropdowns store the selected item's index. The first item is `0`, the next is `1`, and so on.

```php
$choices = ["Peaceful", "Easy", "Normal", "Hard"];
$selected = new Observable(1, true); // Easy

$form = new DDUI($player, "Difficulty");
$form
    ->dropdown("Difficulty", $selected, $choices)
    ->button("Read choice", function(Player $player) use ($selected, $choices) : void{
        $index = (int) $selected->getData();
        $player->sendMessage("Selected: " . $choices[$index]);
    })
    ->closeButton();
$form->show();
```

For descriptions under each choice, use item arrays:

```php
$selected = new Observable(0, true);

$form = new DDUI($player, "Choose a mode");
$form->dropdown("Mode", $selected, [
    ["label" => "Casual", "description" => "Take it easy"],
    ["label" => "Competitive", "description" => "Play for a higher rank"]
]);
$form->closeButton()->show();
```

You can mix strings and item arrays. Values are still indexes, not custom IDs. A dropdown needs between `1` and `128` items. Its item list stays fixed while the form is open.

## Hide or disable a control

Use `visible` to hide a control, or `disabled` to keep an interactive control visible but prevent interaction. Both accept booleans or observables.

```php
$disabled = new Observable(true);
$visible = new Observable(true);

$form = new DDUI($player, "Actions");
$form
    ->button("Unlock action", function(Player $player) use ($disabled) : void{
        $disabled->setData(false);
    })
    ->button("Action", function(Player $player) : void{
        $player->sendMessage("Action received.");
    }, [
        "disabled" => $disabled,
        "visible" => $visible
    ])
    ->closeButton();
$form->show();
```

Use boolean observables for these options. Text descriptions use string observables. The close button has no options and is always visible.

## Live titles

Pass an observable into the constructor:

```php
$title = new Observable("Waiting");

$form = new DDUI($player, $title);
$form
    ->button("Update title", function(Player $player) use ($title) : void{
        $title->setData("Ready!");
    })
    ->closeButton();
$form->show();
```

## React to input changes

Subscribe when you want code to run as an observable changes:

```php
$enabled = new Observable(false, true);
$status = new Observable("Currently disabled");

$unsubscribe = $enabled->subscribe(function(bool $value) use ($status) : void{
    $status->setData($value ? "Currently enabled" : "Currently disabled");
});

$form = new DDUI($player, "Live settings");
$form->label($status)->toggle("Enabled", $enabled)->closeButton();
$form->onClose(function(Player $player, string $reason) use ($unsubscribe) : void{
    $unsubscribe();
});
$form->show();
```

`subscribe()` returns a closure you call to remove your listener. It runs on future changes, not immediately when you subscribe. It also runs for changes made by your own `setData()` calls, and setting an identical value doesn't trigger it again.

The form cleans up its own bindings. Your plugin owns subscriptions it creates. If you keep them in a manager or a task, also release them on player quit and plugin disable, since disconnect doesn't invoke `onClose()`.

## Closing a form

Register a close callback before showing the form:

```php
$form = new DDUI($player, "Example");
$form->label("Close this menu.")->closeButton();
$form->onClose(function(Player $player, string $reason) : void{
    $player->sendMessage("Form closed: " . $reason);
});
$form->show();
```

You can also close it yourself:

```php
if($form->isShowing()){
    $form->close();
}
```

A local `close()` call, including the dedicated close button, reports `server_closed`. Client responses can report reasons such as `clientcanceled`, `userbusy`, or `invalidform`. Closing does not automatically mean the player saved their settings.

Only one `onClose` callback is stored. Registering another replaces the previous one. For a save/cancel menu, save inside an explicit Save button callback, then call `$form->close()`.

Before opening another DDUI form, close the current one and schedule the next opening at least one server tick later. Don't open the next form directly inside `onClose()`.

## A complete settings menu

Put this method inside your plugin class, with the imports from the beginning of the guide. Call `$this->openSettings($player)` from your command handler.

```php
private function openSettings(Player $player) : void{
    $status = new Observable("Change a setting, then press Save.");
    $particles = new Observable(true, true);
    $nickname = new Observable($player->getName(), true);
    $distance = new Observable(8, true);
    $mode = new Observable(0, true);

    $form = new DDUI($player, "My settings");
    $form
        ->header("General")
        ->label($status)
        ->textField("Nickname", $nickname, ["maxBytes" => 32])
        ->toggle("Particles", $particles)
        ->slider("Distance", $distance, 2, 16, 1)
        ->dropdown("Mode", $mode, ["Casual", "Competitive"])
        ->spacer()
        ->button("Reset", function(Player $player) use (
            $status, $particles, $nickname, $distance, $mode
        ) : void{
            $particles->setData(true);
            $nickname->setData($player->getName());
            $distance->setData(8);
            $mode->setData(0);
            $status->setData("Reset. Press Save to apply these values.");
        })
        ->button("Save", function(Player $player) use (
            $form, $particles, $nickname, $distance, $mode
        ) : void{
            $settings = [
                "particles" => $particles->getData(),
                "nickname" => $nickname->getData(),
                "distance" => (int) $distance->getData(),
                "mode" => (int) $mode->getData()
            ];

            $player->sendMessage("Settings: " . json_encode($settings));
            $form->close();
        })
        ->closeButton("Cancel");

    $form->show();
}
```

Hook the collected settings into your own plugin logic. The example prints them so you can see what the player picked.

## Quick reference

| Control | Method | Input value |
| --- | --- | --- |
| Label | `label()` | String or observable text |
| Header | `header()` | String or observable text |
| Spacer | `spacer()` | None |
| Button | `button()` | Player callback |
| Close button | `closeButton()` | Optional label and visibility |
| Toggle | `toggle()` | Client-writable boolean observable |
| Text field | `textField()` | Client-writable string observable |
| Slider | `slider()` | Client-writable numeric observable |
| Dropdown | `dropdown()` | Client-writable numeric index observable |

For a confirmation menu, use a label and two buttons for Confirm and Cancel. The controls listed above are the available building blocks.

Build the layout before `show()`. Once it's open, update the observables you already bound; adding controls while showing throws an exception. A form supports up to `128` layout controls.
