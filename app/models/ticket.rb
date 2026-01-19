# frozen_string_literal: true

class Ticket < ApplicationRecord
  has_many :sla_cycles, class_name: "TicketSlaCycle", dependent: :destroy

  def closed?
    status == "closed" || closed_at.present?
  end

  def current_sla_cycle
    sla_cycles.running.order(started_at: :desc).first
  end

  def start_sla_cycle!(reason:)
    sla_cycles.create!(
      sla_profile_id: sla_profile_id_value,
      started_at: Time.current,
      status: :running,
      reason: reason,
      elapsed_seconds: 0
    )
  end

  private

  def sla_profile_id_value
    respond_to?(:sla_profile_id) ? sla_profile_id : nil
  end
end
