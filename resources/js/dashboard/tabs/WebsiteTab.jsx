import { X } from "lucide-react";
import { useTheme } from "../theme.jsx";
import { Field } from "../ui.jsx";

export default function WebsiteTab({ ctx }) {
  const { colors } = useTheme();
  const { addFaq, newFaq, removeFaq, saveSite, services, setNewFaq, setSiteDraft, siteDraft, toggleFeatured, updateServicePhoto } = ctx;
  return (
    <div className="flex flex-col gap-4 max-w-3xl">
      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div className="font-display text-lg mb-4">Hero section</div>
        <div className="flex flex-col gap-3">
          <Field label="Headline"><input value={siteDraft.heroHeadline} onChange={(e) => setSiteDraft({ ...siteDraft, heroHeadline: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
          <Field label="Subtext"><textarea value={siteDraft.heroSub} onChange={(e) => setSiteDraft({ ...siteDraft, heroSub: e.target.value })} rows={2} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
        </div>
      </div>

      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div className="font-display text-lg mb-1">Featured services</div>
        <div className="dc-subtle text-xs mb-4">Check the ones to show on the landing page, and optionally add a photo for each.</div>
        <div className="flex flex-col gap-3">
          {Object.keys(services).map((name) => (
            <div key={name} style={{ border: `1px solid ${colors.border}` }} className="flex items-center gap-3 p-3 rounded-md flex-wrap">
              <label className="flex items-center gap-2 text-sm w-40 flex-shrink-0">
                <input type="checkbox" checked={siteDraft.featuredServiceNames.includes(name)} onChange={() => toggleFeatured(name)} />
                {name}
              </label>
              <input
                value={services[name].photoUrl || ""}
                onChange={(e) => updateServicePhoto(name, e.target.value)}
                aria-label={`${name} photo URL`}
                placeholder="Photo URL (optional)"
                style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }}
                className="px-2 py-1.5 rounded-md text-sm flex-1 min-w-[180px]"
              />
              {services[name].photoUrl && <img src={services[name].photoUrl} alt="" className="w-10 h-10 rounded-md object-cover flex-shrink-0" />}
            </div>
          ))}
        </div>
      </div>

      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div className="font-display text-lg mb-4">FAQs</div>
        <div className="flex flex-col gap-2 mb-4">
          {siteDraft.faqs.map((f) => (
            <div key={f.id} style={{ border: `1px solid ${colors.border}` }} className="flex items-center justify-between gap-2 px-3 py-2 rounded-md text-sm">
              <span className="truncate">{f.question}</span>
              <button onClick={() => removeFaq(f.id)} aria-label="Remove FAQ" style={{ color: colors.danger }}><X size={14} /></button>
            </div>
          ))}
        </div>
        <div className="flex items-end gap-3 flex-wrap">
          <Field label="Question"><input value={newFaq.question} onChange={(e) => setNewFaq({ ...newFaq, question: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-56" /></Field>
          <Field label="Answer"><input value={newFaq.answer} onChange={(e) => setNewFaq({ ...newFaq, answer: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-64" /></Field>
          <button onClick={addFaq} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">Add</button>
        </div>
      </div>

      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div className="font-display text-lg mb-4">Contact, hours & policy</div>
        <div className="grid sm:grid-cols-2 gap-3 mb-3">
          <Field label="Phone"><input value={siteDraft.contactPhone} onChange={(e) => setSiteDraft({ ...siteDraft, contactPhone: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
          <Field label="Address / map query"><input value={siteDraft.contactAddress} onChange={(e) => setSiteDraft({ ...siteDraft, contactAddress: e.target.value, mapQuery: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
          <Field label="Opens"><input type="time" value={siteDraft.openTime} onChange={(e) => setSiteDraft({ ...siteDraft, openTime: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
          <Field label="Closes"><input type="time" value={siteDraft.closeTime} onChange={(e) => setSiteDraft({ ...siteDraft, closeTime: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
        </div>
        <Field label="Cancellation policy"><textarea value={siteDraft.cancellationPolicy} onChange={(e) => setSiteDraft({ ...siteDraft, cancellationPolicy: e.target.value })} rows={2} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-full" /></Field>
      </div>

      <button onClick={saveSite} className="btn-wine px-4 py-2.5 rounded-md text-sm font-medium self-start">Publish changes to landing page</button>
    </div>
  );
}
