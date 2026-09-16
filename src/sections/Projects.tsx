import ProjectCard from "../components/ProjectCard";
import { projects } from "../data/projects";

export default function Projects() {
  const publishedProjects = projects.filter(
    (project) => project.status === "published",
  );

  return (
    <section id="projects" className="projects-section">
      <div className="projects-container">
        <p className="hero-label">MIJN WERK</p>

        <h2>Uitgelichte projecten</h2>

        <p className="projects-intro">
          Een selectie van mijn werk op het gebied van webdesign en frontend
          development.
        </p>

        <div className="projects-grid">
          {publishedProjects.map((project) => (
            <ProjectCard key={project.id} project={project} />
          ))}
        </div>
      </div>
    </section>
  );
}
