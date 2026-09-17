export default function About() {
  return (
    <section id="about" className="rgb-about">
      <div className="rgb-about__container">
        <div className="rgb-about__intro">
          <p className="hero-label">OVER MIJ</p>

          <h2>
            Creativiteit en techniek.
            <br />
            <span>Van idee naar ervaring.</span>
          </h2>

          <p className="rgb-about__lead">
            Hoi, ik ben Ruben. Met RGB Visuals combineer ik webdesign en
            frontend development om digitale ervaringen te maken die er goed
            uitzien én prettig werken.
          </p>

          <p>
            Ik studeer Communication & Multimedia Design aan de Hanze. Binnen
            mijn werk verbind ik conceptontwikkeling, visueel ontwerp en code.
            Ik vind het interessant om niet alleen iets moois te ontwerpen, maar
            het ook daadwerkelijk te bouwen.
          </p>

          <p>
            Buiten het ontwerpen en ontwikkelen haal ik veel energie uit sport
            en motorsport. Die combinatie van creativiteit, techniek en blijven
            verbeteren neem ik mee in mijn werk.
          </p>

          <a href="/#contact" className="rgb-about__contact">
            Laten we samenwerken
            <span aria-hidden="true">↗</span>
          </a>
        </div>

        <div className="rgb-about__details">
          <div className="rgb-about__detail">
            <span className="rgb-about__number">01</span>
            <div>
              <h3>Webdesign</h3>
              <p>
                Websites ontwerpen met aandacht voor uitstraling, structuur en
                gebruiksgemak.
              </p>
            </div>
          </div>

          <div className="rgb-about__detail">
            <span className="rgb-about__number">02</span>
            <div>
              <h3>Frontend development</h3>
              <p>Ontwerpen vertalen naar responsive, interactieve websites.</p>
            </div>
          </div>

          <div className="rgb-about__detail">
            <span className="rgb-about__number">03</span>
            <div>
              <h3>Concept & UX</h3>
              <p>
                Vanuit een idee onderzoeken hoe vorm, inhoud en interactie
                samenkomen.
              </p>
            </div>
          </div>

          <div className="rgb-about__signature">
            <span>RGB VISUALS</span>
            <strong>Design meets development.</strong>
          </div>
        </div>
      </div>
    </section>
  );
}
