# Brief 📂
**Present your arguments.**

## Goals
The goal of Brief is to make it easier to package up arguments and pass them around. It also makes some attempt to normalize some soft-typing stuff, specifically w/r/t to type-coercion on the boolean value of non-boolean values--i.e., it always wants to use `===` not `==`. It is also intended to help in situations where you are looking for variables but can't be sure if they'll exist or not, and want to avoid errors when they can't be found. Yes, you can use `isset()` but this attempts to abstract that a little bit and save you a few keystrokes.

If you're interested in the history and motivation of this project, it's an attempt to improve on and expand a small tool I made for myself to handle the above problem when dealing with Laravel Blade templates.
