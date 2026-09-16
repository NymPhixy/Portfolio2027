export type Project = {
  id: number;
  title: string;
  description: string;
  year: number;
  image: string;
  technologies: string[];
  status: "draft" | "published";
};
