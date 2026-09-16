import type { Project } from "../types/project";

type ProjectDetailProps = {
  project: Project;
};

export default function ProjectDetail({ project }: ProjectDetailProps) {
  const hasMeta =
    Boolean(project.client) ||
    Boolean(project.role) ||
    project.technologies.length > 0;

  const hasStory =
    Boolean(project.challenge) ||
    Boolean(project.solution) ||
    Boolean(project.result);

  return (
    <section className="project-detail">
      <div className="project-detail-container">
        <a href="/" className="project-back">
          ← Terug naar portfolio
        </a>

        <p className="hero-label">
          CASESTUDY
          {project.year !== null ? ` · ${project.year}` : ""}
        </p>

        <h1>{project.title}</h1>

        <p className="project-detail-intro">{project.description}</p>

        <img
          className="project-detail-cover"
          src={project.image}
          alt={
            project.imageIsPlaceholder
              ? "RGB Visuals standaardafbeelding; projectfoto volgt"
              : `Projectafbeelding van ${project.title}`
          }
        />

        {hasMeta && (
          <div className="project-detail-meta">
            {project.client && (
              <div>
                <h2>Opdrachtgever</h2>
                <p>{project.client}</p>
              </div>
            )}

            {project.role && (
              <div>
                <h2>Mijn rol</h2>
                <p>{project.role}</p>
              </div>
            )}

            {project.technologies.length > 0 && (
              <div>
                <h2>Technologieën</h2>
                <p>{project.technologies.join(", ")}</p>
              </div>
            )}
          </div>
        )}

        {hasStory ? (
          <div className="project-detail-story">
            {project.challenge && (
              <>
                <h2>De uitdaging</h2>
                <p>{project.challenge}</p>
              </>
            )}

            {project.solution && (
              <>
                <h2>De oplossing</h2>
                <p>{project.solution}</p>
              </>
            )}

            {project.result && (
              <>
                <h2>Het resultaat</h2>
                <p>{project.result}</p>
              </>
            )}
          </div>
        ) : (
          <p className="project-detail-intro">
            Een uitgebreide casestudy volgt.
          </p>
        )}

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
