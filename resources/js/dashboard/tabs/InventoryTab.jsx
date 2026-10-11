import { AlertTriangle, Minus, Plus } from "lucide-react";
import { serifFont, useTheme } from "../theme.jsx";
import { Field, IconBtn } from "../ui.jsx";

export default function InventoryTab({ ctx }) {
  const { colors } = useTheme();
  const { addInventoryItem, adjustInventory, inventory, newItem, setNewItem } = ctx;
  return (
    <div className="flex flex-col gap-4">
      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl dash-card overflow-x-auto">
        <table className="w-full text-sm">
          <thead>
            <tr style={{ color: colors.subtle, borderBottom: `1px solid ${colors.border}` }} className="text-left">
              <th className="font-semibold py-3 px-5">Item</th>
              <th className="font-semibold py-3 px-5">Category</th>
              <th className="font-semibold py-3 px-5">Unit cost</th>
              <th className="font-semibold py-3 px-5">Quantity</th>
              <th className="font-semibold py-3 px-5">Status</th>
            </tr>
          </thead>
          <tbody>
            {inventory.map((i) => {
              const low = i.qty <= i.reorder;
              return (
                <tr key={i.id} style={{ borderBottom: `1px solid ${colors.border}` }}>
                  <td className="py-3 px-5 font-medium">{i.name}</td>
                  <td className="py-3 px-5" style={{ color: colors.subtle }}>{i.category}</td>
                  <td className="py-3 px-5" style={{ color: colors.subtle }}>${i.unitCost.toFixed(2)}</td>
                  <td className="py-3 px-5">
                    <div className="flex items-center gap-2">
                      <IconBtn onClick={() => adjustInventory(i.id, -1)} icon={Minus} color={colors.subtle} title="Decrease" />
                      <span className="w-6 text-center">{i.qty}</span>
                      <IconBtn onClick={() => adjustInventory(i.id, 1)} icon={Plus} color={colors.wine} title="Increase" />
                    </div>
                  </td>
                  <td className="py-3 px-5">
                    {low ? (
                      <span style={{ background: colors.dangerSoft, color: colors.danger }} className="flex items-center gap-1 w-fit text-xs px-2 py-1 rounded-md font-medium"><AlertTriangle size={12} /> Low stock</span>
                    ) : (
                      <span style={{ background: colors.successSoft, color: colors.success }} className="text-xs px-2 py-1 rounded-md font-medium w-fit">In stock</span>
                    )}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div style={{ background: colors.cardBg, border: `1px solid ${colors.border}` }} className="rounded-xl p-5 dash-card">
        <div style={{ fontFamily: serifFont }} className="text-lg mb-4">Add inventory item</div>
        <div className="flex items-end gap-3 flex-wrap">
          <Field label="Name"><input value={newItem.name} onChange={(e) => setNewItem({ ...newItem, name: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-44" /></Field>
          <Field label="Category"><input value={newItem.category} onChange={(e) => setNewItem({ ...newItem, category: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-32" /></Field>
          <Field label="Qty"><input type="number" value={newItem.qty} onChange={(e) => setNewItem({ ...newItem, qty: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-20" /></Field>
          <Field label="Reorder at"><input type="number" value={newItem.reorder} onChange={(e) => setNewItem({ ...newItem, reorder: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-24" /></Field>
          <Field label="Unit cost"><input type="number" value={newItem.unitCost} onChange={(e) => setNewItem({ ...newItem, unitCost: e.target.value })} style={{ border: `1px solid ${colors.border}`, background: colors.cardBg, color: colors.ink }} className="px-2 py-1.5 rounded-md text-sm w-24" /></Field>
          <button onClick={addInventoryItem} className="btn-wine px-4 py-2 rounded-md text-sm font-medium">Add item</button>
        </div>
      </div>
    </div>
  );
}
