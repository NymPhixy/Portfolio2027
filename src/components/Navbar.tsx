import { useState } from "react";
import type { KeyboardEvent } from "react";

export default function Navbar() {
  const [isMenuOpen, setIsMenuOpen] = useState(false);

  function closeMenu() {
    setIsMenuOpen(false);
  }

  function handleNavigationKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (event.key === "Escape") {
      closeMenu();
    }
  }

  return (
    <nav className="rgb-navbar" aria-label="Hoofdnavigatie">
      <a href="/#home" className="rgb-navbar__brand">
        RGB Visuals
      </a>

      <button
        type="button"
        className="rgb-navbar__menu-toggle"
        aria-expanded={isMenuOpen}
        aria-controls="rgb-main-navigation"
        aria-label={isMenuOpen ? "Menu sluiten" : "Menu openen"}
        onClick={() => setIsMenuOpen((current) => !current)}
      >
        <span aria-hidden="true" />
        <span aria-hidden="true" />
        <span aria-hidden="true" />
      </button>

      <div
        id="rgb-main-navigation"
        className={`rgb-navbar__links${isMenuOpen ? " is-open" : ""}`}
        onKeyDown={handleNavigationKeyDown}
      >
        <a href="/#home" onClick={closeMenu}>
          Home
        </a>
        <a href="/#projects" onClick={closeMenu}>
          Projecten
        </a>
        <a href="/#about" onClick={closeMenu}>
          Over mij
        </a>
        <a href="/#contact" onClick={closeMenu}>
          Contact
        </a>

        {import.meta.env.DEV && (
          <a
            href="/?admin=preview"
            className="rgb-navbar__studio-login"
            onClick={closeMenu}
          >
            <span aria-hidden="true">✦</span>
            <span>Studio Login</span>
            <span aria-hidden="true">↗</span>
          </a>
        )}
      </div>
    </nav>
  );
}
