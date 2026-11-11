/**
 * Classes for the collapsed desktop rail. Hover or keyboard focus temporarily
 * widens it so labels are readable; the page column stays at the rail width.
 */
export const sidebarPeek = {
  rail:
    'w-64 lg:w-16 lg:hover:w-64 lg:focus-within:w-64 lg:hover:shadow-overlay lg:focus-within:shadow-overlay',
  label: 'lg:group-[:not(:hover):not(:focus-within)]/nav:hidden',
  block: 'lg:group-[:not(:hover):not(:focus-within)]/nav:hidden',
  header:
    'justify-center px-2 lg:group-hover/nav:justify-between lg:group-hover/nav:px-5 lg:group-focus-within/nav:justify-between lg:group-focus-within/nav:px-5',
  nav: 'px-2 lg:group-hover/nav:px-3 lg:group-focus-within/nav:px-3',
  item:
    'px-3 lg:justify-center lg:px-0 lg:group-hover/nav:justify-start lg:group-hover/nav:px-3 lg:group-focus-within/nav:justify-start lg:group-focus-within/nav:px-3',
  footer: 'px-2 lg:group-hover/nav:px-5 lg:group-focus-within/nav:px-5',
  collapseBtn: 'mx-auto lg:group-hover/nav:mx-0 lg:group-focus-within/nav:mx-0',
}
