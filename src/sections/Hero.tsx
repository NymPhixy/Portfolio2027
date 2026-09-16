import { useRef } from "react";
import type { PointerEvent } from "react";

import heroImg from "../assets/hero.png";

export default function Hero() {
  const visualRef = useRef<HTMLDivElement>(null);

  function handlePointerMove(event: PointerEvent<HTMLDivElement>) {
    if (event.pointerType !== "mouse") return;

    if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
      return;
    }

    const visual = visualRef.current;
    if (!visual) return;

    const rect = visual.getBoundingClientRect();

    const x = (event.clientX - rect.left) / rect.width;
    const y = (event.clientY - rect.top) / rect.height;

    visual.style.setProperty("--rgb-tilt-x", `${-(y - 0.5) * 12}deg`);
    visual.style.setProperty("--rgb-tilt-y", `${(x - 0.5) * 16}deg`);

    visual.style.setProperty("--rgb-light-x", `${x * 100}%`);
    visual.style.setProperty("--rgb-light-y", `${y * 100}%`);
  }

  function handlePointerLeave() {
    const visual = visualRef.current;
    if (!visual) return;

    visual.style.setProperty("--rgb-tilt-x", "0deg");
    visual.style.setProperty("--rgb-tilt-y", "0deg");

    visual.style.setProperty("--rgb-light-x", "50%");
    visual.style.setProperty("--rgb-light-y", "50%");
  }

  return (
    <section id="home" className="hero-section rgb-hero">
      <div className="hero-content">
        <p className="hero-label rgb-hero-reveal rgb-delay-1">
          FRONTEND DEVELOPMENT & WEBDESIGN
        </p>

        <h1 className="rgb-hero-title">
          <span className="rgb-hero-title-line rgb-delay-2">
            Hoi, ik ben Ruben.
          </span>

          <span className="rgb-hero-title-line rgb-delay-3">
            Ik bouw digitale
          </span>

          <span className="rgb-hero-title-line rgb-hero-gradient rgb-delay-4">
            ervaringen.
          </span>
        </h1>

        <div className="rgb-hero-reveal rgb-delay-5">
          <p className="hero-description">
            Met RGB Visuals help ik ondernemers hun website professioneler en
            sterker zichtbaar te maken.
          </p>

          <p className="hero-description">
            Van een eerste ontwerp tot een gebruiksvriendelijke website: ik
            combineer creativiteit met frontend development.
          </p>
        </div>

        <div className="hero-actions rgb-hero-reveal rgb-delay-6">
          <a href="#projects" className="button-primary">
            Bekijk mijn projecten
          </a>

          <a href="#contact" className="button-secondary">
            Neem contact op
          </a>
        </div>
      </div>

      <div
        ref={visualRef}
        className="hero-image rgb-hero-visual"
        onPointerMove={handlePointerMove}
        onPointerLeave={handlePointerLeave}
      >
        <div className="rgb-hero-floating">
          <img
            src={heroImg}
            className="rgb-hero-tilt-image"
            alt="Gelaagde illustratie van RGB Visuals"
          />
        </div>
      </div>
    </section>
  );
}
