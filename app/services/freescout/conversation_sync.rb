# frozen_string_literal: true

module Freescout
  class ConversationSync
    def self.call(ticket:, payload:)
      ticket.status = payload["status"]
      ticket.closed_at = parse_timestamp(payload["closedAt"])
      ticket.last_reply_from = payload.dig("customerWaitingSince", "latestReplyFrom")
      ticket.last_activity_at = latest_customer_activity_at(payload)
      ticket.save!
    end

    def self.latest_customer_activity_at(payload)
      threads = payload.dig("_embedded", "threads") || []
      customer_threads = threads.select { |thread| thread["type"] == "customer" }
      latest_thread = customer_threads.max_by { |thread| parse_timestamp(thread["createdAt"]) }

      parse_timestamp(latest_thread && latest_thread["createdAt"])
    end

    def self.parse_timestamp(value)
      return if value.blank?

      Time.zone.parse(value.to_s)
    end
  end
end
