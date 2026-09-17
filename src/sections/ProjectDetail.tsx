import { useEffect, useState } from "react";
import type { Project } from "../types/project";
import type { ProjectGalleryItem } from "../types/project";

type ProjectDetailProps = {
  project: Project;
};

type PublicGalleryResponse = {
  images?: Array<{
    image_url: string;
    alt_text: string | null;
  }>;
  error?: string;
};

function galleryImageDetails(image: ProjectGalleryItem): {
  url: string;
  altText: string | null;
} {
  if (typeof image === "string") {
    return {
      url: image,
      altText: null,
    };
  }

  return {
    url: image.url,
    altText: image.altText ?? null,
  };
}

export default function ProjectDetail({ project }: ProjectDetailProps) {
  const [gallery, setGallery] = useState<ProjectGalleryItem[]>(project.gallery);
  const [galleryLoading, setGalleryLoading] = useState(
    project.cmsProjectId !== undefined,
  );
  const [galleryError, setGalleryError] = useState("");

  useEffect(() => {
    const controller = new AbortController();

    if (project.cmsProjectId === undefined) {
      return () => controller.abort();
    }

    async function loadGallery() {
      try {
        const response = await fetch(
          `/api/projects/${project.cmsProjectId}/images`,
          {
            signal: controller.signal,
            credentials: "same-origin",
            cache: "no-store",
          },
        );
        const data: PublicGalleryResponse = await response.json();

        if (!response.ok || !Array.isArray(data.images)) {
          throw new Error(
            data.error ?? "Projectbeelden konden niet worden geladen.",
          );
        }

        if (!controller.signal.aborted) {
          setGallery(
            data.images.map((image) => ({
              url: image.image_url,
              altText: image.alt_text,
            })),
          );
          setGalleryLoading(false);
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          setGalleryError(
            error instanceof Error
              ? error.message
              : "Projectbeelden konden niet worden geladen.",
          );
          setGalleryLoading(false);
        }
      }
    }

    void loadGallery();

    return () => controller.abort();
  }, [project.cmsProjectId]);

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

        {galleryLoading && (
          <p className="project-detail-intro" role="status">
            Projectbeelden laden...
          </p>
        )}

        {galleryError && (
          <p className="project-detail-intro" role="alert">
            {galleryError}
          </p>
        )}

        {!galleryLoading && !galleryError && gallery.length > 0 && (
          <div className="project-detail-gallery">
            <h2>Projectbeelden</h2>

            <div className="project-gallery-grid">
              {gallery.map((image, index) => {
                const imageDetails = galleryImageDetails(image);

                return (
                  <img
                    key={`${imageDetails.url}-${index}`}
                    src={imageDetails.url}
                    alt={
                      imageDetails.altText ||
                      `${project.title}, afbeelding ${index + 1}`
                    }
                    loading="lazy"
                  />
                );
              })}
            </div>
          </div>
        )}
      </div>
    </section>
  );
}
