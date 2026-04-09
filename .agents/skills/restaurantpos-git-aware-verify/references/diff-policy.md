# Diff Policy

## Default source order

1. Explicit paths passed on the command line
2. Git diff data from unstaged, staged, and untracked files
3. Stdin path list when Git is unavailable

## With `--base`

- Compare `base...HEAD` to get the branch-level diff.
- Also include local staged, unstaged, and untracked files so in-progress work is not hidden.
- Call out the base ref in the final summary.

## No Git worktree

- Fall back to explicit paths or stdin.
- If neither is provided, the wrapper must fail loudly instead of returning an empty recommendation set.
