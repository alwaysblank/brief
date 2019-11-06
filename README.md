# Brief 📂
**Present your arguments.**

[![Build Status](https://travis-ci.org/alwaysblank/brief.svg?branch=master)](https://travis-ci.org/alwaysblank/brief)

## Goals
The goal of Brief is to make it easier to package up arguments and pass them around. It also makes some attempt to normalize some soft-typing stuff, specifically w/r/t to type-coercion on the boolean value of non-boolean values--i.e., it always wants to use `===` not `==`. It is also intended to help in situations where you are looking for variables but can't be sure if they'll exist or not, and want to avoid errors when they can't be found. Yes, you can use `isset()` but this attempts to abstract that a little bit and save you a few keystrokes.

If you're interested in the history and motivation of this project, it's an attempt to improve on and expand a small tool I made for myself to handle the above problem when dealing with Laravel Blade templates.

## Functionality

- Replicate all `Props` functionality 
    - Receive arrays of values
        - Also accepts an object w/ public properties
    - Access values as dynamic properties
    - Return bool `false` for unset values
- `debrief` method, which invokes the passed `callable` (i.e. function name, method, anonymous/lambda function) with the arguments in a Brief
    - `pass` method, which uses **[argument unpacking](https://secure.php.net/manual/en/migration56.new-features.php#migration56.new-features.splat)** to pass arguments to a function as separate variables instead of one Brief--useful when working w/ functions/methods that don't have Brief support.
- Some way to define some properties about arguments:
    - Argument order on output--necessary for methods like `pass` to work, possibly for other reasons.
    - Expected arguments--again necessary for `pass`, but likely useful in other contexts too.
- Allow for "fallback" keys when using `find` method.
- System for modifying Brief behavior through a `settings` array passed at instantiation.
- Allow for key aliases via `settings`. Aliases support both getting and setting.
- Create an empty Brief that won't throw Exceptions on instantiation with `Brief::empty()`, `new EmptyBrief()`, or `EmptyBrief::make()`.
