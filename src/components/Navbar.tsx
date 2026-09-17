export default function Navbar() {
  return (
    <nav className="rgb-navbar">
      <a href="/#home" className="rgb-navbar__brand">
        RGB Visuals
      </a>

      <div className="rgb-navbar__links">
        <a href="/#home">Home</a>
        <a href="/#projects">Projecten</a>
        <a href="/#about">Over mij</a>
        <a href="/#contact">Contact</a>

        {import.meta.env.DEV && (
          <a href="/?admin=preview" className="rgb-navbar__studio-login">
            <span aria-hidden="true">✦</span>
            <span>Studio Login</span>
            <span aria-hidden="true">↗</span>
          </a>
        )}
      </div>
    </nav>
  );
}
