import { createFileRoute, notFound } from "@tanstack/react-router";
import { ServicePage } from "@/components/service-page";
import { services } from "@/lib/services";

export const Route = createFileRoute("/diensten/$dienst")({
  loader: ({ params }) => {
    const service = services.find((item) => item.slug === params.dienst);
    if (!service) throw notFound();
    return service;
  },
  head: ({ loaderData: service }) => {
    if (!service) return {};
    const title = `${service.title} — Zichtbaar Marketing`;
    const url = `https://www.zichtbaar-marketing.nl/diensten/${service.slug}`;
    return {
      meta: [
        { title },
        { name: "description", content: service.intro },
        { property: "og:title", content: title },
        { property: "og:description", content: service.intro },
        { property: "og:url", content: url },
      ],
      links: [{ rel: "canonical", href: url }],
    };
  },
  component: () => <ServicePage service={Route.useLoaderData()} />,
});
