import type { Project } from "../types/project";

type ProjectDetailProps = {
  project: Project;
};

export default function ProjectDetail({ project }: ProjectDetailProps) {
  return (
    <section className="project-detail">
      <div className="project-detail-container">
        <a href="/" className="project-back">
          ← Terug naar portfolio
        </a>

        <p className="hero-label">CASESTUDY · {project.year}</p>
        <h1>{project.title}</h1>
        <p className="project-detail-intro">{project.description}</p>

        <img
          className="project-detail-cover"
          src={project.image}
          alt={`Projectafbeelding van ${project.title}`}
        />

        <div className="project-detail-meta">
          <div>
            <h2>Opdrachtgever</h2>
            <p>{project.client}</p>
          </div>

          <div>
            <h2>Mijn rol</h2>
            <p>{project.role}</p>
          </div>

          <div>
            <h2>Technologieën</h2>
            <p>{project.technologies.join(", ")}</p>
          </div>
        </div>

        <div className="project-detail-story">
          <h2>De uitdaging</h2>
          <p>{project.challenge}</p>

          <h2>De oplossing</h2>
          <p>{project.solution}</p>

          <h2>Het resultaat</h2>
          <p>{project.result}</p>
        </div>

        {project.gallery.length > 0 && (
          <div className="project-detail-gallery">
            <h2>Projectbeelden</h2>

            <div className="project-gallery-grid">
              {project.gallery.map((image, index) => (
                <img
                  key={`${image}-${index}`}
                  src={image}
                  alt={`${project.title} – afbeelding ${index + 1}`}
                  loading="lazy"
                />
              ))}
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
