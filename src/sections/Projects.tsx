import { useEffect, useRef } from "react";

import ProjectCard from "../components/ProjectCard";
import type { Project } from "../types/project";

type ProjectsProps = {
  projects: Project[];
};

export default function Projects({ projects }: ProjectsProps) {
  const gridRef = useRef<HTMLDivElement>(null);

  const publishedProjects = projects.filter(
    (project) => project.status === "published",
  );

  useEffect(() => {
    const grid = gridRef.current;

    if (!grid) return;

    const prefersReducedMotion = window.matchMedia(
      "(prefers-reduced-motion: reduce)",
    ).matches;

    if (prefersReducedMotion || !("IntersectionObserver" in window)) {
      return;
    }

    const cards = grid.querySelectorAll<HTMLElement>(".project-card");

    if (cards.length === 0) return;

    grid.classList.add("rgb-scroll-ready");

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;

          entry.target.classList.add("rgb-in-view");
          observer.unobserve(entry.target);
        });
      },
      {
        threshold: 0.12,
        rootMargin: "0px 0px -35px 0px",
      },
    );

    cards.forEach((card) => observer.observe(card));

    return () => {
      observer.disconnect();
      grid.classList.remove("rgb-scroll-ready");
    };
  }, [projects]);

  return (
    <section id="projects" className="projects-section">
      <div className="projects-container">
        <p className="hero-label">MIJN WERK</p>

        <h2>Uitgelichte projecten</h2>

        <p className="projects-intro">
          Een selectie van mijn werk op het gebied van webdesign en frontend
          development.
        </p>

        <div ref={gridRef} className="projects-grid">
          {publishedProjects.map((project) => (
            <ProjectCard key={project.id} project={project} />
          ))}
        </div>
      </div>
    </section>
  );
}
