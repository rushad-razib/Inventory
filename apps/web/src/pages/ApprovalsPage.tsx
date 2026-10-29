import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";
import {
  approveAdjustment,
  approvePurchaseOrder,
  listApprovals,
  rejectAdjustment,
  rejectPurchaseOrder,
} from "../lib/api";
import { prettyLabel } from "../lib/labels";
import { formatTaka } from "../lib/money";
import styles from "./catalog.module.css";

export function ApprovalsPage() {
  const queryClient = useQueryClient();
  const [error, setError] = useState<string | null>(null);
  const approvalsQuery = useQuery({ queryKey: ["approvals"], queryFn: listApprovals });

  const refresh = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: ["approvals"] }),
      queryClient.invalidateQueries({ queryKey: ["purchase-orders"] }),
      queryClient.invalidateQueries({ queryKey: ["adjustments"] }),
      queryClient.invalidateQueries({ queryKey: ["stock-balances"] }),
    ]);
  };

  const mutation = useMutation({
    mutationFn: async (action: { kind: "order" | "adjustment"; id: string; decision: "approve" | "reject" }) => {
      if (action.kind === "order") {
        return action.decision === "approve" ? approvePurchaseOrder(action.id) : rejectPurchaseOrder(action.id);
      }
      return action.decision === "approve" ? approveAdjustment(action.id) : rejectAdjustment(action.id);
    },
    onSuccess: async () => {
      setError(null);
      await refresh();
    },
    onError: (err: Error) => setError(err.message),
  });

  const orders = approvalsQuery.data?.purchase_orders ?? [];
  const adjustments = approvalsQuery.data?.adjustments ?? [];
  const empty = approvalsQuery.data && orders.length === 0 && adjustments.length === 0;

  return (
    <div className={styles.page}>
      <header className={styles.header}>
        <div>
          <h1 className={styles.title}>Approvals</h1>
          <p className={styles.subtitle}>Purchase orders and adjustments waiting for someone other than the requester.</p>
        </div>
      </header>
      {approvalsQuery.isLoading ? <p className={styles.muted}>Loading…</p> : null}
      {approvalsQuery.isError ? <p className={styles.error}>{(approvalsQuery.error as Error).message}</p> : null}
      {empty ? <p className={styles.muted}>Nothing is waiting for approval.</p> : null}
      {orders.length > 0 ? (
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Purchase orders</h2>
          {orders.map((order) => (
            <div className={styles.detailRow} key={order.id}>
              <span>
                <span className="mono">{order.number}</span> · {order.supplier.name} · {formatTaka(order.total)}
              </span>
              <DecisionButtons
                busy={mutation.isPending}
                onApprove={() => mutation.mutate({ kind: "order", id: order.id, decision: "approve" })}
                onReject={() => mutation.mutate({ kind: "order", id: order.id, decision: "reject" })}
              />
            </div>
          ))}
        </section>
      ) : null}
      {adjustments.length > 0 ? (
        <section className={styles.panel}>
          <h2 className={styles.panelTitle}>Adjustments</h2>
          {adjustments.map((adjustment) => (
            <div className={styles.detailRow} key={adjustment.id}>
              <span>
                <span className="mono">{adjustment.product.sku}</span> · {adjustment.quantity} · {prettyLabel(adjustment.reason)}
              </span>
              <DecisionButtons
                busy={mutation.isPending}
                onApprove={() => mutation.mutate({ kind: "adjustment", id: adjustment.id, decision: "approve" })}
                onReject={() => mutation.mutate({ kind: "adjustment", id: adjustment.id, decision: "reject" })}
              />
            </div>
          ))}
        </section>
      ) : null}
      {error ? <p className={styles.error}>{error}</p> : null}
    </div>
  );
}

function DecisionButtons({
  busy,
  onApprove,
  onReject,
}: {
  busy: boolean;
  onApprove: () => void;
  onReject: () => void;
}) {
  return (
    <span className={styles.actions}>
      <button className={styles.button} type="button" disabled={busy} onClick={onApprove}>
        Approve
      </button>
      <button className={styles.buttonSecondary} type="button" disabled={busy} onClick={onReject}>
        Reject
      </button>
    </span>
  );
}
