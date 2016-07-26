## Contributions to AceIDE
AceIDE would not be the powerful tool it is without your contributions. Community contributions are essential to ensure the continued development of the editor. This document outlines the requirements for contributing to the AceIDE project.

### Coding Standards
Code for the AceIDE project should abide by the [WordPress Coding Standard][1]. This ensures code styling is consistant across the codebase.

### Contributing code
In order to have your modifications accepted, a pull request must be submitted to the [AceIDE GitHub repository][2]. It must meet the coding standards outlined above, and it must have a meaningful commit message.

### Making a meaningful commit message
Commits are a way of communicating to other developers what it is that your commit is changing. Meaningful commit messages are important in that they allow us to review your commit, and better understand what it does before going through the code.

A good commit  message always starts with a brief but descriptive title. What follows is a more verbose body that may go into more detail to explain what has been changed.

As an example, the following commit fixes an issue, referenced by issue ID 42, which fixes a bug introduced in commit abcd123.

```
(#42) Fix admin menu disappearing on hover

Commit abcd123 introduces a bug where the admin menu may disappear when
the mouse hovers over it for more than 3 seconds. This commit keeps the
intended functionality of making it hidden in the editor window, whilst
fixing this issue for all other pages in the admin panel.
```

  [1]: https://codex.wordpress.org/WordPress_Coding_Standards
  [2]: https://github.com/AceIDE/AceIDE
