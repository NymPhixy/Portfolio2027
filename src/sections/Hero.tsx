import heroImg from "../assets/hero.png";

export default function Hero() {
  return (
    <section id="home" className="hero-section">
      <div className="hero-content">
        <p className="hero-label">FRONTEND DEVELOPMENT & WEBDESIGN</p>

        <h1>
          Hoi, ik ben Ruben.
          <br />
          Ik bouw digitale ervaringen.
        </h1>

        <p className="hero-description">
          Met RGB Visuals help ik ondernemers hun website professioneler en
          sterker zichtbaar te maken.
        </p>

        <p className="hero-description">
          Van een eerste ontwerp tot een gebruiksvriendelijke website: ik
          combineer creativiteit met frontend development.
        </p>

        <div className="hero-actions">
          <a href="#projects" className="button-primary">
            Bekijk mijn projecten
          </a>

          <a href="#contact" className="button-secondary">
            Neem contact op
          </a>
        </div>
      </div>

      <div className="hero-image">
        <img src={heroImg} alt="RGB Visuals hero-afbeelding" />
      </div>
    </section>
  );
}
