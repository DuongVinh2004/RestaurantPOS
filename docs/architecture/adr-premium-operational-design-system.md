# ADR: Premium Operational Design System

## Context
The POS UI must be fast, highly readable, and usable under stressful, fast-paced restaurant conditions (including on tablets and by users relying on keyboards). Flashy aesthetics (glassmorphism, neon glows, slow animations) detract from operational efficiency.

## Decision
1. **Prioritize Speed and Clarity**: The design system will focus on high contrast, clear typography, and instant feedback.
2. **Tokens**: We will define standard tokens for typography, spacing, radius, shadows, and semantic colors (Success, Info, Warning, Danger, Pending, Offline, Syncing, Conflict, Disabled, Critical Alert).
3. **Strict Touch Targets**: All interactive elements must meet accessible touch target sizes (minimum 44x44px for primary actions) to support tablet usage.
4. **No Distractive Styling**: Explicitly avoid glassmorphism, blur, neon glows, long animations, and low-contrast gradients. Ensure support for `prefers-reduced-motion`.
5. **Keyboard Accessibility**: Focus rings must be prominent. All destructive actions require explicit confirmation, and core paths must be navigable via keyboard.
6. **State Representation**: Status must be communicated through text/icons alongside color—never color alone. Empty states must provide guidance; loading states must be unambiguous.

## Consequences
- Requires a cohesive set of Tailwind configuration and Ant Design theme overrides.
- Ensures the interface feels premium through its responsiveness and clarity rather than superficial decoration.
