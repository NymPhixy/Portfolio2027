export type ProjectGalleryImage = {
  url: string;
  altText?: string | null;
};

export type ProjectGalleryItem = string | ProjectGalleryImage;

export type Project = {
  id: number | string;
  title: string;
  description: string;
  year: number | null;
  image: string;
  imageIsPlaceholder?: boolean;
  technologies: string[];
  status: "draft" | "published";

  // Inhoud voor de casestudy
  client: string;
  role: string;
  challenge: string;
  solution: string;
  result: string;
  gallery: ProjectGalleryItem[];
  cmsProjectId?: number;
};
