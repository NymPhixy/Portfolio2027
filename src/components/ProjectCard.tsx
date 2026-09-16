import type { Project } from "../types/project";

type ProjectCardProps = {
  project: Project;
};

export default function ProjectCard({ project }: ProjectCardProps) {
  return (
    <article className="project-card">
      <a
        href={`/?project=${project.id}`}
        className="project-card-link"
        aria-label={`Bekijk de casestudy van ${project.title}`}
      >
        <img
          src={project.image}
          alt={`Voorbeeldafbeelding van ${project.title}`}
          className="project-card-image"
        />

        <div className="project-card-content">
          <span className="project-year">{project.year}</span>

          <h3>{project.title}</h3>

          <p>{project.description}</p>

          <div className="project-technologies">
            {project.technologies.map((technology) => (
              <span key={technology}>{technology}</span>
            ))}
          </div>

          <span className="project-card-cta">Bekijk casestudy →</span>
        </div>
      </a>
    </article>
  );
}
