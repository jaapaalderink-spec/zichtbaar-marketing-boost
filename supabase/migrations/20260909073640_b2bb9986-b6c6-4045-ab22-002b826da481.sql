CREATE TABLE public.contact_messages (
  id UUID NOT NULL DEFAULT gen_random_uuid() PRIMARY KEY,
  bedrijfsnaam TEXT,
  naam TEXT NOT NULL,
  telefoon TEXT,
  email TEXT NOT NULL,
  bericht TEXT,
  gelezen BOOLEAN NOT NULL DEFAULT false,
  created_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()
);

GRANT INSERT ON public.contact_messages TO anon;
GRANT SELECT, INSERT, UPDATE, DELETE ON public.contact_messages TO authenticated;
GRANT ALL ON public.contact_messages TO service_role;

ALTER TABLE public.contact_messages ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Anyone can submit a contact message"
  ON public.contact_messages FOR INSERT TO anon, authenticated
  WITH CHECK (
    length(naam) BETWEEN 1 AND 100
    AND length(email) BETWEEN 3 AND 255
    AND (bericht IS NULL OR length(bericht) <= 2000)
    AND (bedrijfsnaam IS NULL OR length(bedrijfsnaam) <= 150)
    AND (telefoon IS NULL OR length(telefoon) <= 40)
  );

CREATE POLICY "Authenticated users can read messages"
  ON public.contact_messages FOR SELECT TO authenticated USING (true);

CREATE POLICY "Authenticated users can update messages"
  ON public.contact_messages FOR UPDATE TO authenticated USING (true) WITH CHECK (true);

CREATE POLICY "Authenticated users can delete messages"
  ON public.contact_messages FOR DELETE TO authenticated USING (true);