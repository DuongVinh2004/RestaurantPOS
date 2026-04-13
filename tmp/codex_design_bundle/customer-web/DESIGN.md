# DESIGN.md — RestaurantPOS Customer Web

## 1. Visual Theme & Atmosphere
This interface should feel:
- warm
- premium
- trustworthy
- mobile-first
- easy to browse
- easy to act on

Primary influences:
- Airbnb for warmth, rounded surfaces, and image-friendly discovery
- Apple for premium whitespace and polished presentation
- Stripe for payment clarity and trust
- Uber for direct, action-oriented flow

The product should help customers move smoothly from discovery to order confirmation.

## 2. Product Intent
This is a customer-facing commerce experience.

Design for:
- menu discovery
- promo/combo visibility
- clear customization
- fast add-to-cart
- frictionless checkout
- trust during payment
- clear post-purchase feedback

## 3. Color Palette & Roles
Base neutrals:
- Background: #FAFAF8
- Surface 1: #FFFFFF
- Surface 2: #F3F4F6
- Surface 3: #EAECEF
- Border: #E5E7EB

Text:
- Primary: #101828
- Secondary: #475467
- Tertiary: #667085
- Inverse: #FFFFFF

Brand warmth:
- Coral: #FF5A5F
- Coral hover: #F24B51
- Coral soft: #FFE6E8

Premium accents:
- Ink black: #111111
- Graphite: #1F2937

Payment / trust:
- Stripe purple: #635BFF
- Stripe soft: #ECEBFF
- Success green: #16A34A
- Warning amber: #F59E0B
- Danger red: #DC2626

Usage:
- Coral leads discovery, browse, highlights, and active storefront actions.
- Stripe purple is used for payment trust moments, checkout emphasis, and authenticated flows.
- Black and graphite provide premium anchoring.
- Use green for confirmed/completed states only.

## 4. Typography Rules
Font stack:
- Inter, ui-sans-serif, system-ui, sans-serif

Hierarchy:
- Hero title: 40/44, semibold
- Section title: 28/32, semibold
- Card title: 18/24, semibold
- Body: 16/24, regular
- Secondary copy: 14/22, regular
- Price emphasis: 18/24 or 20/28, semibold
- Micro labels: 12/16, medium

Rules:
- Keep headlines strong but not overly wordy.
- Use concise selling copy.
- Prioritize readability on mobile.
- Numeric price formatting must be visually stable.

## 5. Layout Principles
- Mobile-first storefront
- Clear content stacking
- Large touch-friendly interactive zones
- Sections should feel breathable and premium
- Desktop expands the layout without losing clarity

Spacing scale:
- 4, 8, 12, 16, 20, 24, 32, 40, 56

Page rhythm:
1. Discovery / context
2. Category navigation
3. Product listing
4. Cart access
5. Checkout progression
6. Confirmation / next step

## 6. Depth & Elevation
- Light surfaces with subtle shadow
- Cards feel tactile and approachable
- Sticky action areas should feel anchored
- Modals and drawers should feel clean and unobtrusive

Shadows:
- Cards: soft
- Elevated cart or floating CTA: medium soft
- Dialogs: medium

Radius:
- Buttons: 12px
- Inputs: 12px
- Cards: 18px
- Sheets / drawers: 20px

## 7. Component Stylings

### Navigation
- Navigation should feel simple and welcoming
- Sticky areas are acceptable if they reduce friction
- Category chips/tabs should be easy to tap
- Cart access must remain visible

### Hero / Discovery
- Use premium but efficient storytelling
- Highlight offers, categories, and trust quickly
- Avoid cluttered banners

### Product Cards
- Strong hierarchy: image, name, short descriptor, price, CTA
- Rounded cards
- Images should feel appetizing but clean
- Cards should work in both dense and relaxed layouts

### Buttons
Variants:
- Primary storefront action: coral
- Checkout / payment emphasis: purple where trust matters
- Secondary: bordered neutral
- Ghost: subtle, clean, low emphasis

Rules:
- CTAs must be obvious on mobile
- Too many equal-weight buttons should be avoided
- Add-to-cart should be effortless

### Inputs & Forms
- High clarity
- Large tap targets
- Strong focus and error states
- Inline validation and helpful microcopy
- Payment and address/contact forms should feel calm and trustworthy

### Cart
- Cart should be transparent, editable, and easy to scan
- Line items need clean spacing
- Price summary must be unmistakable
- Delivery / pickup / table options must be understandable quickly

### Checkout
- Stripe-like trust, structure, and cleanliness
- Minimize cognitive load
- Group fields logically
- Keep confirmation details visible
- Support mobile completion comfortably

### Status & Tracking
- Track order state with clear step-based language
- Confirmation pages should reduce anxiety
- Use friendly reassurance and concise next-step messaging

### Promo & Membership
- Surface promos without feeling spammy
- Membership/wallet/loyalty moments should feel premium but simple

## 8. Interaction Principles
- Discovery should feel inviting.
- Ordering should feel fast.
- Checkout should feel safe.
- Confirmation should feel reassuring.
- Mobile use is the default case.
- Images support appetite and trust, not clutter.

## 9. Domain-Specific Guidance
Suitable for:
- landing page
- menu browse
- category tabs
- product detail
- customization flow
- promo/combo pages
- cart
- checkout
- reservation or booking flow
- order confirmation
- order tracking
- loyalty / profile / order history

## 10. Do
- use warm, polished, conversion-friendly hierarchy
- keep actions obvious
- keep the cart easy to reach
- keep images clean and high-value
- use whitespace to create premium feel
- use clear trust signals around payment and confirmation

## 11. Do Not
- do not turn customer pages into enterprise dashboards
- do not overload the UI with dense admin controls
- do not use too many accent colors at once
- do not bury the add-to-cart path
- do not make checkout visually noisy
- do not use sterile layouts with no warmth
