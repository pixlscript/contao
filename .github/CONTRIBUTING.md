# Contributing to Contao

The following is a set of guidelines for contributing to Contao and its
packages, which are hosted in the [Contao organization][1] on GitHub. These
are just guidelines, not rules, use your best judgement and feel free to
propose changes to this document in a pull request.

## Issues

 * Use the search function to see if a similar issue has already been
   submitted.
 * Describe the issue in detail and include all the steps to follow in order to
   reproduce the bug in the [online demo][2].
 * Include the version of Contao and PHP you are using.
 * Include screenshots if possible; they are immensely helpful.
 * If you are reporting a bug, please include any related error message you are
   seeing and also check the `var/logs/` directory for related log files.

## Pull requests

 * Follow the Contao coding standards.
 * For new features, create your pull request against the `4.x` branch.
 * For bug fixes, create your pull request against the lowest affected branch
   that is actively supported, e.g. `4.9` if the bug is in Contao 4.9 or `4.12`
   if the bug is only in Contao 4.12 or greater.
 * Include screenshots in your pull request whenever possible.
 * If you want to add a new feature, we recommend that you discuss your ideas
   with us before your start writing code; either on GitHub or in one of our
   [monthly calls][3].

## Git commit messages

 * Use the present tense ("Add feature" not "Added feature").
 * Use the imperative mood ("Move cursor to …" not "Moves cursor to …").
 * Reference issues and pull requests liberally.

[1]: https://github.com/contao
[2]: https://demo.contao.org/contao
[3]: https://contao.org/en/mumble-calls.html
